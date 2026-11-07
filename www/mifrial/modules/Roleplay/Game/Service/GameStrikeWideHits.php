<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeTargetOptions;
use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Урон и итоги целей широкого удара.
 */
final class GameStrikeWideHits
{
    /**
     * Создаёт расчёт итогов.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeResolution $resolution Расчёт урона.
     * @param GameStrikeSupport $support Поддержки строки.
     * @param GameStrikeTargetClose $targets Расчёт цели.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeSupport $support,
        private readonly GameStrikeTargetClose $targets,
    ) {
    }

    /**
     * Считает урон и записи всех целей.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     *
     * @return array{damage: DimensionalNumber, results: list<array<string, mixed>>} Итоги.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    public function hits(GameStrikeWideRun $run, array $open, array $prepared, array $opening): array
    {
        $damage = $this->damage($run, $open, $opening['attackerRoll']);
        $penetration = $this->penetration($run, $open);

        return [
            'damage' => $damage,
            'results' => $this->results($run, $open, $prepared, $opening, $damage, $penetration),
        ];
    }

    /**
     * Общий урон удара или ноль при авто-провале.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     *
     * @return DimensionalNumber Урон.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если профиль битый.
     */
    private function damage(GameStrikeWideRun $run, array $open, array $attackerRoll): DimensionalNumber
    {
        return $this->rules->hitCheck->isHitAutoFail($attackerRoll['roll'])
            ? $this->amounts->zero()
            : $this->resolution->damage->damageOf($run->game, $open);
    }

    /**
     * Ленивое проникновение удара.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return callable(): DimensionalNumber Фабрика.
     */
    private function penetration(GameStrikeWideRun $run, array $open): callable
    {
        $penetration = null;

        return function () use (&$penetration, $run, $open): DimensionalNumber {
            if (!$penetration instanceof DimensionalNumber) {
                $penetration = $this->resolution->defense->penetrationOf($run->game, $open);
            }

            return $penetration;
        };
    }

    /**
     * Записи всех целей широкого удара.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     * @param DimensionalNumber $damage Урон.
     * @param callable(): DimensionalNumber $penetration Проникновение.
     *
     * @return list<array<string, mixed>> Записи.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    private function results(
        GameStrikeWideRun $run, array $open, array $prepared, array $opening, DimensionalNumber $damage, callable $penetration,
    ): array {
        $results = [];
        foreach ($prepared['rows'] as $index => $row) {
            $results[] = $this->result($run, $open, $prepared, $opening, $row, $index, $damage, $penetration);
        }

        return $results;
    }

    /**
     * Запись одной цели широкого удара.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{choices: list<array<string, mixed>>, refusals: list<string|null>, prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>, covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>, early: array<string, mixed>|null} $prepared Подготовка.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     * @param array<string, mixed> $row Строка.
     * @param int $index Индекс.
     * @param DimensionalNumber $damage Урон.
     * @param callable(): DimensionalNumber $penetration Проникновение.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или лист битые.
     */
    private function result(
        GameStrikeWideRun $run, array $open, array $prepared, array $opening,
        array $row, int $index, DimensionalNumber $damage, callable $penetration,
    ): array {
        $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
        $state = $prepared['resolved'][$rowId] ?? null;
        $supports = $this->support->advantages($row);

        return $this->targets->one(
            $run->game,
            $open,
            $row,
            $prepared['choices'][$index],
            $prepared['refusals'][$index] ?? null,
            $damage,
            is_array($state['attackerRoll'] ?? null) ? $state['attackerRoll'] : $opening['attackerRoll'],
            $opening['hitCode'],
            $penetration,
            $run->scope,
            new GameStrikeTargetOptions(
                $supports['defender'],
                $state === null ? ($prepared['prepared'][$rowId] ?? null) : null,
                $state,
                $run->battle->getMarks(),
            ),
        );
    }
}
