<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Броски защиты, урон и проверка цели широкого удара.
 */
final class GameStrikeTargetRolls
{
    /**
     * Создаёт расчёт цели.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeResolution $resolution Расчёт бросков и урона.
     * @param GameStrikeAmounts $amounts Пары и нули.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeAmounts $amounts,
    ) {
    }

    /**
     * Броски защиты и число успеха цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice|null $slice Срез.
     * @param string $hitCode Код проверки.
     * @param array<string, mixed> $conditions Условия цели.
     * @param RollAdvantage|null $token Преимущество жетона концентрации.
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества защиты.
     *
     * @return array{defenderRoll: array<string, mixed>|null, autoFail: bool, success: int}
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function defenseOf(
        GameRecord $game, array $open, array $target, array $choice, array $attackerRoll,
        array $pending, ?string $reactionRuleCode, ?CharacterRuleSlice $slice, string $hitCode,
        array $conditions, ?RollAdvantage $token = null, array $extraAdvantages = [],
    ): array {
        $advantages = $this->advantages($conditions, $token, $extraAdvantages);
        $slice ??= $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        ['defenderRoll' => $defenderRoll, 'ratedRoll' => $ratedRoll] = $this->defenderRoll(
            $game, $open, $target, $choice, $attackerRoll, $pending, $reactionRuleCode, $slice, $hitCode, $advantages,
        );

        return [
            'defenderRoll' => $defenderRoll,
            'autoFail' => $this->rules->hitCheck->isHitAutoFail($attackerRoll['roll']),
            'success' => $this->resolution->successOf($ratedRoll, $defenderRoll, $choice['reaction']),
        ];
    }

    /**
     * Защита без нового броска по уже готовому результату.
     *
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param string $reaction Реакция.
     *
     * @return array{defenderRoll: array<string, mixed>|null, autoFail: bool, success: int}
     */
    public function resolvedDefense(array $attackerRoll, ?array $defenderRoll, string $reaction): array
    {
        return [
            'defenderRoll' => $defenderRoll,
            'autoFail' => $this->rules->hitCheck->isHitAutoFail($attackerRoll['roll']),
            'success' => $this->resolution->successOf($attackerRoll, $defenderRoll, $reaction),
        ];
    }

    /**
     * Урон, сопротивление и повреждение цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param int $success Успех.
     * @param DimensionalNumber $damage Урон.
     * @param callable(): DimensionalNumber $penetrationFactory Ленивое проникновение.
     * @param array<string, mixed> $pending Карта ожидания атакующего.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     *
     * @return array{effectiveDamage: DimensionalNumber, resistance: DimensionalNumber,
     *               soak: DimensionalNumber|null, durabilityShave: int, injury: DimensionalNumber,
     *               appliedSr: int, srCap: int|null}
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function damageOf(
        GameRecord $game, array $open, array $target, array $choice, ?array $defenderRoll,
        int $success, DimensionalNumber $damage, callable $penetrationFactory,
        array $pending, array $attackerRoll,
    ): array {
        $targetOpen = GameStrikeTargetRow::targetOpen($open, $target);
        $durabilityShave = $this->durabilityShave($game, $open, $attackerRoll, $success);
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;
        $resistance = $this->resistance(
            $game, $targetOpen, $choice, $defenderRoll, $success, $penetrationFactory, $durabilityShave,
        );
        $soak = $this->resolution->damage->soakOf($game, $targetOpen, $choice['reaction'], $success, $pending);
        $damageTypeCode = $this->damageTypeCode($game, $open);
        $srCap = $this->rules->outcome->successRatingCap($game->getSpaceId(), $game->getRulesRevision(), $damageTypeCode);
        $appliedSr = $this->amounts->appliedSr($success, $srCap);

        return [
            'effectiveDamage' => $effectiveDamage,
            'resistance' => $resistance,
            'soak' => $soak,
            'durabilityShave' => $durabilityShave,
            'injury' => $this->amounts->injury($effectiveDamage, $resistance, $appliedSr, $soak),
            'appliedSr' => $appliedSr,
            'srCap' => $srCap,
        ];
    }

    /**
     * Бросок атакующего с помехой широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param GameBattleRecord $battle Бой.
     * @param CharacterRuleSlice $slice Срез.
     * @param int $targetCount Число целей.
     * @param array<int, string> $targetKeys Ключи целей.
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества атакующего из поддержек.
     *
     * @return array<string, mixed> Roll.
     */
    public function rollOf(
        GameRecord $game,
        array $open,
        GameBattleRecord $battle,
        CharacterRuleSlice $slice,
        int $targetCount,
        array $targetKeys = [],
        array $extraAdvantages = [],
    ): array {
        $hitCode = $this->rules->hitCheck->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $advantages = $this->wideAdvantages($game, $open, $targetCount, $extraAdvantages);

        return $this->resolution->rolls->rollOf(
            $game,
            $open,
            $hitCode,
            $battle->getPendingActionEffects(),
            $slice,
            $advantages,
            $targetKeys,
        );
    }

    /**
     * Помехи защиты цели.
     *
     * @param array<string, mixed> $conditions Условия цели.
     * @param RollAdvantage|null $token Жетон концентрации.
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества поддержек.
     *
     * @return array<int, RollAdvantage> Помехи.
     */
    private function advantages(array $conditions, ?RollAdvantage $token, array $extraAdvantages): array
    {
        $advantages = ($conditions['flank'] ?? false) === true && ($conditions['turn'] ?? false) !== true
            ? [new RollAdvantage('flank', -2, AdvantageSourceLabels::getLabel('flank'))]
            : [];
        if ($token !== null) {
            $advantages[] = $token;
        }

        array_push($advantages, ...$extraAdvantages);

        return $advantages;
    }

    /**
     * Пересчитанный бросок атаки и бросок защиты.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $hitCode Код проверки.
     * @param array<int, RollAdvantage> $advantages Помехи.
     *
     * @return array{defenderRoll: array<string, mixed>|null, ratedRoll: array<string, mixed>}
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function defenderRoll(
        GameRecord $game, array $open, array $target, array $choice, array $attackerRoll,
        array $pending, ?string $reactionRuleCode, CharacterRuleSlice $slice, string $hitCode, array $advantages,
    ): array {
        $targetOpen = GameStrikeTargetRow::targetOpen($open, $target);
        $defenderRoll = null;
        $ratedRoll = $attackerRoll;
        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->resolution->rolls->dodgeRollOf(
                $game, $targetOpen, $hitCode, $attackerRoll, $pending, $reactionRuleCode, $slice, $advantages,
            );
            $ratedRoll = $this->rules->hitCheck->rerate($attackerRoll, $defenderRoll);
        } elseif ($choice['reaction'] === 'block') {
            $defenderRoll = $this->resolution->rolls->blockRollOf(
                $game, $targetOpen, $choice, $hitCode, $attackerRoll, $pending, $reactionRuleCode, $slice, $advantages,
            );
        }

        return ['defenderRoll' => $defenderRoll, 'ratedRoll' => $ratedRoll];
    }

    /**
     * Проникновение и сопротивление цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $targetOpen Строка цели.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param int $success Успех.
     * @param callable(): DimensionalNumber $penetrationFactory Ленивое проникновение.
     * @param int $durabilityShave Срез надёжности.
     *
     * @return DimensionalNumber Сопротивление.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function resistance(
        GameRecord $game,
        array $targetOpen,
        array $choice,
        ?array $defenderRoll,
        int $success,
        callable $penetrationFactory,
        int $durabilityShave,
    ): DimensionalNumber {
        if ($success === 0) {
            return $this->amounts->zero();
        }

        return $this->resolution->defense->resistanceOf(
            $game,
            $targetOpen,
            $this->resolution->defense->resistanceChoice($choice, $defenderRoll),
            $success,
            $penetrationFactory(),
            $durabilityShave,
        );
    }

    /**
     * Срез надёжности с единиц урона.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param int $success Успех.
     *
     * @return int Срез.
     */
    private function durabilityShave(GameRecord $game, array $open, array $attackerRoll, int $success): int
    {
        if ($success === 0) {
            return 0;
        }

        return $this->rules->outcome->durabilityShave(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
            is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '',
            is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0,
            $attackerRoll,
            $success,
        );
    }

    /**
     * Тип урона профиля.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return string|null Тип урона.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если профиль битый.
     */
    private function damageTypeCode(GameRecord $game, array $open): ?string
    {
        return $this->rules->outcome->damageTypeCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '',
            is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '',
            is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0,
        );
    }

    /**
     * Преимущества широкого броска.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $targetCount Число целей.
     * @param array<int, RollAdvantage> $extraAdvantages Преимущества поддержек.
     *
     * @return array<int, RollAdvantage> Преимущества.
     */
    private function wideAdvantages(GameRecord $game, array $open, int $targetCount, array $extraAdvantages): array
    {
        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $advantages = [];
        $wideAdvantage = $this->rules->cards->wideTargetCheckAdvantage(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actionRuleCode,
            $targetCount,
        );
        if ($wideAdvantage !== null) {
            $advantages[] = $wideAdvantage;
        }

        array_push($advantages, ...$extraAdvantages);

        return $advantages;
    }
}
