<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Цели, сопротивление и последствия взрыва — одна причина изменения.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\SpellDeviationBurst;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Взрыв при дубле отклонения: урон по списку целей и их последствия.
 */
final class GameSpellDeviationBurst
{
    /**
     * Создаёт расчёт взрыва.
     *
     * @param GameStrikeRules $rules Срез и данные.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param ICharacterSheets $characterSheets Пересчёт листа.
     * @param GameStrikeDefense $defense Сопротивление.
     * @param GameStrikeAmounts $amounts Арифметика урона.
     * @param GameStrikeConsequences $consequences Последствия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly ICharacterSheets $characterSheets,
        private readonly GameStrikeDefense $defense,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeConsequences $consequences,
    ) {
    }

    /**
     * Урон и операции по каждой цели взрыва.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $deviation Исход отклонения.
     * @param SpellDeviationBurst $burst Карточка взрыва.
     * @param array<int, array{type: string, id: int, distanceIpari: int}> $targets Цели.
     *
     * @return list<array<string, mixed>> Строки целей.
     *
     * @throws GameInvalidException Если тип урона или лист битые.
     */
    public function plan(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        array $deviation,
        SpellDeviationBurst $burst,
        array $targets,
    ): array {
        $damage = $this->damageSpec($prepared, $burst->getDamageTypeCode());
        $power = (new DimensionalNumber($prepared->usedPower->getBase(), $prepared->usedPower->getSize()))->toInteger();
        $digit = is_int($deviation['dieDigit'] ?? null) ? max(1, $deviation['dieDigit']) : 1;
        $rows = [];
        foreach ($targets as $target) {
            $rows[] = $this->target($game, $prepared, $damage, $burst, $target, $power, $digit);
        }

        return $rows;
    }

    /**
     * Одна цель взрыва.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param DamageTypeSpec $damage Тип урона.
     * @param SpellDeviationBurst $burst Карточка взрыва.
     * @param array{type: string, id: int, distanceIpari: int} $target Цель.
     * @param int $power Мощь в целых.
     * @param int $digit Цифра на кубиках.
     *
     * @return array<string, mixed> Строка цели.
     *
     * @throws GameInvalidException Если лист битый.
     */
    private function target(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        DamageTypeSpec $damage,
        SpellDeviationBurst $burst,
        array $target,
        int $power,
        int $digit,
    ): array {
        $snapshot = $this->sheets->snapshot($game, $target['type'], $target['id']);
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        $choices = $snapshot['sheet']['choices'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($sheet) || !is_array($choices) || !is_int($version)) {
            throw new GameInvalidException('Game spell burst sheet is invalid');
        }

        $sheet = $this->characterSheets->compute($prepared->slice, $choices, $sheet);
        $amount = new DimensionalNumber(max(0, ($power - max(0, $target['distanceIpari'])) * $digit), 0);
        $resistance = $this->defense->resistanceByType($game, $burst->getDamageTypeCode(), $sheet, $choices);
        $injury = $this->amounts->injury($amount, $resistance, $burst->getSuccessRating(), null);
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
            $burst->getSuccessRating(),
            $split,
            $burst->getDamageTypeCode(),
            true,
            $endurance,
        );

        return [
            'target' => ['type' => $target['type'], 'id' => $target['id']],
            'version' => $version,
            'damage' => $amount,
            'injury' => $injury,
            'operations' => $effects['operations'],
            'consequence' => $effects['consequence'],
            'damageName' => $damage->getGenitive(),
        ];
    }

    /**
     * Тип урона взрыва из среза.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param string $code Код типа.
     *
     * @return DamageTypeSpec Spec.
     *
     * @throws GameInvalidException Если карточки нет.
     */
    private function damageSpec(GameSpellCastPrepared $prepared, string $code): DamageTypeSpec
    {
        $spec = $prepared->slice->findLive($code)?->getSpec();
        if (!$spec instanceof DamageTypeSpec) {
            throw new GameInvalidException('Game spell burst damage type is invalid');
        }

        return $spec;
    }
}
