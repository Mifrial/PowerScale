<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;

/**
 * Подготовка строк широкого удара к расчёту.
 */
final class GameStrikeWidePrepare
{
    /**
     * Создаёт подготовку.
     *
     * @param GameStrikeTargetClose $targets Расчёт цели.
     * @param GameStrikeCover $cover Броски прикрытия.
     * @param GameStrikeWideChecks $checks Проверки целей.
     * @param GameStrikeWideResume $resume Возобновление строк.
     * @param GameStrikeWidePause $pause Пауза на выборе цели.
     * @param GameStrikeCloseRows $rows Строки удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeTargetClose $targets,
        private readonly GameStrikeCover $cover,
        private readonly GameStrikeWideChecks $checks,
        private readonly GameStrikeWideResume $resume,
        private readonly GameStrikeWidePause $pause,
        private readonly GameStrikeCloseRows $rows,
    ) {
    }

    /**
     * Проверяет листы, готовит реакции и решает прикрытие по строкам.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     *
     * @return array{choices: list<array<string, mixed>>, refusals: list<string|null>,
     *               prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>,
     *               covers: array<int, array<string, mixed>|null>, rows: list<array<string, mixed>>,
     *               early: array<string, mixed>|null} Подготовка.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или карточка битые.
     */
    public function prepare(GameStrikeWideRun $run, array $open, array $rows, array $opening): array
    {
        $choices = $this->answers($rows);
        $refusals = $this->checks->refusals(
            $run->game,
            $run->battle->getId(),
            $rows,
            $choices,
            $run->scope->actorUserId,
            $run->scope->viewAll,
        );
        $choices = $this->checks->resolve($run->game, $rows, $choices, $refusals);
        $this->targets->begin($run->battle->getPendingActionEffects(), $run->battle->getConcentrationCycle());
        $scan = $this->scan($run, $open, $rows, $choices, $refusals, $opening);
        $early = $scan['pauses'] === []
            ? null
            : $this->pause->pause($run, $this->strikeId($open), $opening['attackerRoll'], $scan['pauses'], $this->attacker($open));

        return [
            'choices' => $scan['choices'],
            'refusals' => $refusals,
            'prepared' => $scan['prepared'],
            'resolved' => $scan['resolved'],
            'covers' => $scan['covers'],
            'rows' => $scan['rows'],
            'early' => $early,
        ];
    }

    /**
     * Защиты строк из сохранённых ответов.
     *
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return list<array<string, mixed>> Защиты.
     */
    private function answers(array $rows): array
    {
        $choices = [];
        foreach ($rows as $index => $row) {
            $choices[$index] = $this->rows->answerOfRow($row);
        }

        return $choices;
    }

    /**
     * Проходит строки, восстанавливая или подготавливая каждую.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<string|null> $refusals Отказы.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     *
     * @return array{rows: list<array<string, mixed>>, choices: list<array<string, mixed>>,
     *               prepared: array<int, array<string, mixed>>, resolved: array<int, array<string, mixed>>,
     *               covers: array<int, array<string, mixed>|null>, pauses: array<int, array<string, mixed>>} Скан.
     */
    private function scan(
        GameStrikeWideRun $run,
        array $open,
        array $rows,
        array $choices,
        array $refusals,
        array $opening,
    ): array {
        $state = [
            'rows' => $rows,
            'choices' => $choices,
            'prepared' => [],
            'resolved' => [],
            'covers' => [],
            'pauses' => [],
        ];
        foreach ($rows as $index => $row) {
            $this->advance($run, $open, $row, $index, $state, $refusals, $opening);
        }

        return $state;
    }

    /**
     * Обрабатывает одну строку.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<string, mixed> $state Состояние.
     * @param list<string|null> $refusals Отказы.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     *
     * @return void
     */
    private function advance(
        GameStrikeWideRun $run,
        array $open,
        array $row,
        int $index,
        array &$state,
        array $refusals,
        array $opening,
    ): void {
        $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
        if ($rowId < 1) {
            return;
        }

        if (isset($run->resume[$rowId])) {
            $this->restore($run, $row, $index, $rowId, $state);
            return;
        }

        if (($refusals[$index] ?? null) !== null) {
            return;
        }

        $state['prepared'][$rowId] = $this->prepareRow($run, $open, $row, $state['choices'][$index], $opening);
        $cover = $state['prepared'][$rowId]['cover'];
        if (is_array($cover)) {
            $this->cover($row, $index, $rowId, $cover, $state);
        }
    }

    /**
     * Восстанавливает строку по решению окна.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $row Строка.
     * @param int $index Индекс.
     * @param int $rowId Строка.
     * @param array<string, mixed> $state Состояние.
     *
     * @return void
     */
    private function restore(GameStrikeWideRun $run, array $row, int $index, int $rowId, array &$state): void
    {
        $restored = $this->resume->state($row, $run->resume[$rowId]);
        $state['rows'][$index] = $restored['row'];
        $state['choices'][$index] = $restored['choice'];
        $state['resolved'][$rowId] = $restored['resolved'];
        if ($restored['cover'] !== null) {
            $state['covers'][$rowId] = $restored['cover'];
        }

        $this->resume->persist($rowId, $restored['row']);
    }

    /**
     * Готовит реакцию и прикрытие строки.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Защита.
     * @param array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string, rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} $opening Начало.
     *
     * @return array<string, mixed> Подготовка строки.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка или карточка битые.
     */
    private function prepareRow(
        GameStrikeWideRun $run,
        array $open,
        array $row,
        array $choice,
        array $opening,
    ): array {
        return $this->targets->prepare(
            $run->game,
            GameStrikeTargetRow::targetOpen($open, GameStrikeTargetRow::defender($row)),
            $row,
            $choice,
            $opening['hitCode'],
            $opening['attackerRoll'],
            is_string($row['profile_type'] ?? null) ? $row['profile_type'] : 'strike',
            false,
            null,
            $run->battle->getMarks(),
        );
    }

    /**
     * Учитывает прикрытие строки и переносит цель на победителя.
     *
     * @param array<string, mixed> $row Строка.
     * @param int $index Индекс.
     * @param int $rowId Строка.
     * @param array<string, mixed> $cover Прикрытие.
     * @param array<string, mixed> $state Состояние.
     *
     * @return void
     */
    private function cover(array $row, int $index, int $rowId, array $cover, array &$state): void
    {
        $primaryKey = $this->targetKey(GameStrikeTargetRow::defender($row));
        $winner = is_array($cover['winner'] ?? null) ? $cover['winner'] : null;
        $winnerKey = $winner === null ? null : $this->targetKey($winner['actor']);
        $state['covers'][$rowId] = $this->cover->info($cover['candidates'], $primaryKey, $winnerKey);
        if ($winner === null) {
            $state['pauses'][$rowId] = [
                'candidates' => $cover['candidates'],
                'primaryKey' => $primaryKey,
                'target' => GameStrikeTargetRow::defender($row),
                'choice' => $state['choices'][$index],
                'prepared' => $state['prepared'][$rowId],
            ];
            return;
        }

        $state['resolved'][$rowId] = $this->resume->resolved($winner);
        $state['rows'][$index] = $this->resume->rewrite($row, $winner);
        $state['choices'][$index] = $state['resolved'][$rowId]['choice'];
        $this->resume->persist($rowId, $state['rows'][$index]);
    }

    /**
     * Ключ цели.
     *
     * @param array{type: string, id: int} $target Цель.
     *
     * @return string Ключ.
     */
    private function targetKey(array $target): string
    {
        return $target['type'] . ':' . $target['id'];
    }

    /**
     * Идентификатор действия удара.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return int Действие.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если строка битая.
     */
    private function strikeId(array $open): int
    {
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new \Mifrial\Roleplay\Game\Exception\GameInvalidException('Game wide strike is not open');
        }

        return $strikeId;
    }

    /**
     * Атакующий из строки удара.
     *
     * @param array<string, mixed> $open Открытый удар.
     *
     * @return array{type: string, id: int} Атакующий.
     */
    private function attacker(array $open): array
    {
        return [
            'type' => is_string($open['attacker_kind'] ?? null) ? $open['attacker_kind'] : '',
            'id' => is_int($open['attacker_id'] ?? null) ? $open['attacker_id'] : 0,
        ];
    }
}
