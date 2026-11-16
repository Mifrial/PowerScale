<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Сопротивление, последствия и запись листа прыжка — одна причина изменения.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Урон прыжка цепи по выбранной цели.
 *
 * Сначала цель тратит остаток успеха каста на своё Сопротивление магии:
 * отрицательный остаток — цель избегает заклинания. Затем считается урон
 * по записанному состоянию цепи. Лист цели читается текущим, как у взрыва.
 */
final class GameSpellChainDamage
{
    /**
     * Создаёт расчёт прыжка.
     *
     * @param GameStrikeRules $rules Срез и данные.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param ICharacterSheets $characterSheets Пересчёт листа.
     * @param GameStrikeDefense $defense Сопротивление.
     * @param GameStrikeAmounts $amounts Арифметика урона.
     * @param GameSpellMagicResistance $magicResistance Сопротивление магии.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameSpellChainHop $hop Правила прыжка.
     * @param GameSpellChainPending $pending Состояние цепи.
     * @param GameSpellChainForce $force Остаток успеха против сопротивления.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly ICharacterSheets $characterSheets,
        private readonly GameStrikeDefense $defense,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameSpellMagicResistance $magicResistance,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameSpellChainHop $hop,
        private readonly GameSpellChainPending $pending,
        private readonly GameSpellChainForce $force = new GameSpellChainForce(),
    ) {
    }

    /**
     * Прыжок по цели: трата успеха, урон и операции.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $chain Состояние цепи.
     * @param array{type: string, id: int, distanceIpari: int} $target Цель.
     *
     * @return array{row: array<string, mixed>|null, rating: int, spend: int, avoided: bool} Прыжок.
     *
     * @throws GameInvalidException Если тип урона или лист битые.
     */
    public function hop(GameRecord $game, array $chain, array $target): array
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $type = $slice->findLive((string) $chain['damageTypeCode'])?->getSpec();
        if (!$type instanceof DamageTypeSpec) {
            throw new GameInvalidException('Game spell chain damage type is invalid');
        }

        $snapshot = $this->sheets->snapshot($game, $target['type'], $target['id']);
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        $choices = $snapshot['sheet']['choices'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($sheet) || !is_array($choices) || !is_int($version)) {
            throw new GameInvalidException('Game spell chain sheet is invalid');
        }

        $sheet = $this->characterSheets->compute($slice, $choices, $sheet);
        $force = $this->force->spend(
            (int) $chain['rating'],
            $this->magicResistance->sumResistance($game, $slice, $sheet, $choices),
            (int) $chain['penetration'],
        );
        if ($force['avoided']) {
            return ['row' => null, 'rating' => $force['rating'], 'spend' => $force['spend'], 'avoided' => true];
        }

        $amount = $this->hop->nextAmount(
            $this->pending->chainSpec($chain),
            $this->pending->damageSpec($chain),
            $this->pending->amount($chain),
            $target['distanceIpari'],
        );
        if (!$amount instanceof CharacteristicNumber) {
            return ['row' => null, 'rating' => $force['rating'], 'spend' => $force['spend'], 'avoided' => false];
        }

        $code = (string) $chain['damageTypeCode'];
        $spellSr = (int) $chain['spellSr'];
        $resistance = $this->defense->resistanceByType($game, $code, $sheet, $choices);
        $penetration = (int) $chain['penetration'];
        if ($penetration > 0 && $this->magicResistance->isMagicType($slice, $code)) {
            $resistance = $this->amounts->effectiveDefense(
                $resistance,
                new DimensionalNumber($penetration, 0),
            );
        }

        $injury = $this->amounts->injury($amount, $resistance, $spellSr, null);
        $npcSheet = $target['type'] === 'npc' ? $sheet : null;
        $npcChoices = $target['type'] === 'npc' ? $choices : null;
        $split = $this->rules->outcome->splitInjury(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['type'],
            $target['id'],
            $npcSheet,
            $injury,
            $npcChoices,
            $target['type'] === 'npc' ? $version : null,
        );
        $endurance = $this->rules->data->enduranceOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['type'],
            $target['id'],
            $npcSheet,
            $npcChoices,
            $target['type'] === 'npc' ? $version : null,
        );
        $effects = $this->consequences->effects(
            $game,
            $sheet,
            $injury,
            $spellSr,
            $split,
            $code,
            true,
            $endurance,
        );

        return [
            'row' => [
                'target' => ['type' => $target['type'], 'id' => $target['id']],
                'version' => $version,
                'damage' => $amount,
                'injury' => $injury,
                'resistance' => $resistance,
                'endurance' => $endurance,
                'defenseIgnored' => $type->isDefenseIgnored(),
                'successRating' => $spellSr,
                'operations' => $effects['operations'],
                'consequence' => $effects['consequence'],
                'damageName' => $type->getGenitive(),
            ],
            'rating' => $force['rating'],
            'spend' => $force['spend'],
            'avoided' => false,
        ];
    }
}
