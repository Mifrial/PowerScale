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
 * Считает по записанному состоянию цепи: остаток урона, карточка урона
 * и РУ первого попадания. Лист цели читается текущим, как у взрыва.
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
     * @param GameSpellMagicResistance $magicResistance Признак магии.
     * @param GameStrikeConsequences $consequences Последствия.
     * @param GameSpellChainHop $hop Правила прыжка.
     * @param GameSpellChainPending $pending Состояние цепи.
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
    ) {
    }

    /**
     * Урон и операции прыжка по цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $chain Состояние цепи.
     * @param array{type: string, id: int, distanceIpari: int} $target Цель.
     *
     * @return array<string, mixed>|null Строка цели или null, если цепь оборвалась.
     *
     * @throws GameInvalidException Если тип урона или лист битые.
     */
    public function hop(GameRecord $game, array $chain, array $target): ?array
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $type = $slice->findLive((string) $chain['damageTypeCode'])?->getSpec();
        if (!$type instanceof DamageTypeSpec) {
            throw new GameInvalidException('Game spell chain damage type is invalid');
        }

        $amount = $this->hop->nextAmount(
            $this->pending->chainSpec($chain),
            $this->pending->damageSpec($chain),
            $this->pending->amount($chain),
            $target['distanceIpari'],
        );
        if (!$amount instanceof CharacteristicNumber) {
            return null;
        }

        $snapshot = $this->sheets->snapshot($game, $target['type'], $target['id']);
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        $choices = $snapshot['sheet']['choices'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($sheet) || !is_array($choices) || !is_int($version)) {
            throw new GameInvalidException('Game spell chain sheet is invalid');
        }

        $sheet = $this->characterSheets->compute($slice, $choices, $sheet);
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
        ];
    }
}
