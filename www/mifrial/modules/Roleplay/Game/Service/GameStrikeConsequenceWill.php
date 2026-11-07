<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IInjuryEfficiency;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Проверки Воли, увечья и добровольные броски последствий.
 */
final class GameStrikeConsequenceWill
{
    /**
     * Создаёт проверки последствий.
     *
     * @param GameStrikeConsequenceStates $states Состояния.
     * @param GameStateCheck $stateCheck Проверка прироста состояния.
     * @param GameStateDecline $decline Шкала decline.
     * @param GameCheckRoll $checks Бросок с заданной трудностью.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeConsequenceStates $states,
        private readonly GameStateCheck $stateCheck,
        private readonly GameStateDecline $decline,
        private readonly GameCheckRoll $checks,
    ) {
    }

    /**
     * Воля при изменении истощения. Рост без сознания — пустой итог; спад проверяется.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param int $delta Изменение истощения.
     *
     * @return array{
     *     roll: array<string, mixed>|null,
     *     operation: array<string, mixed>|null,
     *     outcome: string|null,
     *     operations: list<array<string, mixed>>,
     *     declineCodes: list<string>
     * }
     */
    public function forDelta(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $delta): array
    {
        if ($delta === 0 || ($delta > 0 && $this->states->has($slice, $sheet, 'unconscious'))) {
            return ['roll' => null, 'operation' => null, 'outcome' => null, 'operations' => [], 'declineCodes' => []];
        }

        $will = $this->will($game, $slice, $sheet, $delta);
        $will['operations'] = $this->decline->operations(
            $sheet,
            $slice,
            $will['operation'],
            $will['declineCodes'],
        );

        return $will;
    }

    /**
     * Проверка Воли при изменении истощения и операция decline.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param int $quotient Частное урона.
     *
     * @return array{
     *     roll: array<string, mixed>,
     *     operation: array<string, mixed>|null,
     *     outcome: string,
     *     declineCodes: list<string>
     * }
     *
     * @throws GameInvalidException Если карточка Воли или состояние битые.
     */
    public function will(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $quotient): array
    {
        $this->requireWillpower($slice);
        $exhaustion = $this->states->single($slice, 'exhaustion');
        $checkCode = $this->willCheckCode($slice, $exhaustion);
        $check = $this->stateCheck->check($slice, $exhaustion, $checkCode);
        $after = $this->states->stored($sheet, $exhaustion) + $quotient;
        $rolled = $this->stateCheck->roll($game, $sheet, $slice, $exhaustion, $checkCode, $after);
        $next = $this->willNext($slice, $check, $rolled);
        $current = $this->decline->current($slice, $sheet, $check);
        $operation = $this->decline->change($quotient > 0, $current, $next);

        return [
            'roll' => $rolled['roll'],
            'operation' => $operation,
            'outcome' => $this->decline->kind($next),
            'declineCodes' => $this->decline->codes($slice, $check),
        ];
    }

    /**
     * Бросок увечья по коду состояния и трудности.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param int $difficulty Трудность.
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     *
     * @return array<string, mixed> Бросок.
     *
     * @throws GameInvalidException Если карточка увечья битая.
     */
    public function maim(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $difficulty, array $hooks): array
    {
        $code = $this->states->single($slice, 'maim');
        $spec = $this->states->spec($slice, $code);
        $checkCode = $spec->getCheckCode();
        if ($checkCode === null || $checkCode === '') {
            $checkCode = $this->injuryCheckCode($slice);
        }

        if ($spec->getValueType() !== 'number' || $checkCode === null) {
            throw new GameInvalidException('Game strike maim check is invalid');
        }

        $roll = $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            ['base' => $difficulty, 'size' => 0],
            new GameCheckThrowOverride(null, 4, $this->injuryAdvantage($hooks)),
        );
        $roll['label'] = $slice->findLive($checkCode)?->getName() ?? 'Проверка на увечье';

        return $roll;
    }

    /**
     * Добровольная проверка способности с уже посчитанной трудностью.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param string $checkCode Проверка.
     * @param int $difficulty Сложность.
     *
     * @return array<string, mixed> Бросок.
     *
     * @throws GameInvalidException Если карточка проверки битая.
     */
    public function optionalCheck(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $sheet,
        string $checkCode,
        int $difficulty,
    ): array {
        $roll = $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            ['base' => $difficulty, 'size' => 0],
        );
        $roll['label'] = $slice->findLive($checkCode)?->getName() ?? $checkCode;

        return $roll;
    }

    /**
     * Код проверки Воли из спеки истощения.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $exhaustion Код состояния истощения.
     *
     * @return string Код проверки.
     *
     * @throws GameInvalidException Если проверка не задана.
     */
    private function willCheckCode(CharacterRuleSlice $slice, string $exhaustion): string
    {
        $spec = $this->states->spec($slice, $exhaustion);
        $checkCode = $spec->getIncreaseCheckCode() ?? $spec->getCheckCode();
        if ($checkCode === null || $checkCode === '') {
            throw new GameInvalidException('Game strike will check is invalid');
        }

        return $checkCode;
    }

    /**
     * Исход проверки Воли: состояние по исходу или старый код по флагам.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param CheckSpec $check Карточка проверки.
     * @param array{roll: array<string, mixed>, outcome: string|null} $rolled Бросок и исход.
     *
     * @return array{code: string, rank: int}|null
     *
     * @throws GameInvalidException Если исход битый.
     */
    private function willNext(CharacterRuleSlice $slice, CheckSpec $check, array $rolled): ?array
    {
        $outcome = $rolled['outcome'];
        if ($outcome !== null) {
            $spec = $this->states->spec($slice, $outcome);
            $rank = $this->decline->rankInCheck($check, $outcome);
            if ($spec->getValueType() !== 'flag' || $rank === null) {
                throw new GameInvalidException('Game strike will outcome is invalid');
            }

            return ['code' => $outcome, 'rank' => $rank];
        }

        if ($check->getOutcomes() === []) {
            $roll = $rolled['roll'];

            return $this->legacyOutcomeCode($slice, $roll['rating']);
        }

        return null;
    }

    /**
     * Старый исход по флагам decline_* для ревизий без outcomes.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param int $rating Рейтинг броска.
     *
     * @return array{code: string, rank: int}|null
     *
     * @throws GameInvalidException Если исход неоднозначен или битый.
     */
    private function legacyOutcomeCode(CharacterRuleSlice $slice, int $rating): ?array
    {
        if ($rating >= 0) {
            return null;
        }

        $flag = $rating <= -3 ? 'unconscious' : ($rating === -2 ? 'disabled' : 'weakness');
        $code = $this->legacyCode($slice, $flag);
        $spec = $this->states->spec($slice, $code);
        $rank = $this->rank($spec);
        if ($spec->getValueType() !== 'flag' || $rank === null) {
            throw new GameInvalidException('Game strike will outcome is invalid');
        }

        return ['code' => $code, 'rank' => $rank];
    }

    /**
     * Единственный код состояния для старого флага decline.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $flag Флаг.
     *
     * @return string Код состояния.
     *
     * @throws GameInvalidException Если код не найден или неоднозначен.
     */
    private function legacyCode(CharacterRuleSlice $slice, string $flag): string
    {
        $matched = null;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof StateSpec || !$this->legacyMatches($spec, $flag)) {
                continue;
            }

            if ($matched !== null) {
                throw new GameInvalidException('Game strike will outcome is invalid');
            }

            $matched = $rule->getCode();
        }

        if (!is_string($matched)) {
            throw new GameInvalidException('Game strike will outcome is invalid');
        }

        return $matched;
    }

    /**
     * Соответствует ли состояние старому флагу decline.
     *
     * @param StateSpec $spec Спека состояния.
     * @param string $flag Флаг.
     *
     * @return bool Признак соответствия.
     */
    private function legacyMatches(StateSpec $spec, string $flag): bool
    {
        return match ($flag) {
            'unconscious' => $spec->isDeclineUnconscious(),
            'disabled' => $spec->isDeclineDisabled(),
            default => $spec->isDeclineWeakness(),
        };
    }

    /**
     * Ранг флага decline: слабость, недееспособность или без сознания.
     *
     * @param StateSpec $spec Спека состояния.
     *
     * @return int|null Ранг или null, если флаг не decline.
     */
    private function rank(StateSpec $spec): ?int
    {
        $flags = [$spec->isDeclineWeakness(), $spec->isDeclineDisabled(), $spec->isDeclineUnconscious()];
        if (count(array_filter($flags)) !== 1) {
            return null;
        }

        if ($spec->isDeclineWeakness()) {
            return 1;
        }

        return $spec->isDeclineDisabled() ? 2 : 3;
    }

    /**
     * Требует ровно одну характеристику Воли в срезе.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     *
     * @return void
     *
     * @throws GameInvalidException Если Воля не найдена или неоднозначна.
     */
    private function requireWillpower(CharacterRuleSlice $slice): void
    {
        $matched = 0;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && $spec->isWillpower()) {
                $matched++;
            }
        }

        if ($matched !== 1) {
            throw new GameInvalidException('Game strike willpower is invalid');
        }
    }

    /**
     * Сумма помех injury_efficiency. Нет delta в payload — −1.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     *
     * @return int Сумма delta.
     *
     * @throws GameInvalidException Если delta не число.
     */
    private function injuryAdvantage(array $hooks): int
    {
        $delta = 0;
        foreach ($hooks as $hook) {
            if (!$hook['handler'] instanceof IInjuryEfficiency) {
                continue;
            }

            $value = $hook['payload']['delta'] ?? -1;
            if (!is_int($value)) {
                throw new GameInvalidException('Game strike hook is invalid');
            }

            $delta += $value;
        }

        return $delta;
    }

    /**
     * Код проверки увечья `check-injury` из среза правил.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     *
     * @return string|null Код проверки или null.
     */
    private function injuryCheckCode(CharacterRuleSlice $slice): ?string
    {
        $rule = $slice->findLive('check-injury');
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CheckSpec) {
            return null;
        }

        return $rule->getCode();
    }
}
