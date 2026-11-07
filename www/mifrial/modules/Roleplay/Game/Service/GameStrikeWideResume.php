<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Возобновление широкого удара после выбора фактической цели.
 */
final class GameStrikeWideResume
{
    /**
     * Создаёт восстановление строк.
     *
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeCover $cover Броски прикрытия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
        private readonly GameStrikeCover $cover,
    ) {
    }

    /**
     * Ожидания строк, для которых ещё нет решения.
     *
     * @param list<array<string, mixed>> $rows Строки.
     * @param array<int, array<string, mixed>> $resume Решения.
     *
     * @return list<array<string, mixed>> Ожидания.
     */
    public function waiting(array $rows, array $resume): array
    {
        $waiting = [];
        foreach ($rows as $row) {
            $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
            if ($rowId < 1 || isset($resume[$rowId])) {
                continue;
            }

            array_push($waiting, ...$this->awaitings($row));
        }

        return $waiting;
    }

    /**
     * Восстанавливает бросок атаки из ожидающих строк.
     *
     * @param list<array<string, mixed>> $rows Строки.
     * @param array<int, array<string, mixed>> $resume Решения.
     *
     * @return array<string, mixed>|null Бросок или null.
     */
    public function attackerRoll(array $rows, array $resume): ?array
    {
        foreach ($rows as $row) {
            $rowId = $row['id'] ?? null;
            if (!is_int($rowId) || !isset($resume[$rowId])) {
                continue;
            }

            $roll = $this->json($row, 'resolution')['attackerRoll'] ?? null;
            if (is_array($roll)) {
                return $roll;
            }
        }

        return null;
    }

    /**
     * Восстанавливает состояние строки после выбора фактической цели.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $resume Решение.
     *
     * @return array{row: array<string, mixed>, choice: array<string, mixed>,
     *               resolved: array<string, mixed>, cover: array<string, mixed>|null} Состояние.
     *
     * @throws GameInvalidException Если решение окна битое.
     */
    public function state(array $row, array $resume): array
    {
        $resolution = $this->json($row, 'resolution');
        $key = $resume['actualTarget'] ?? null;
        if (!is_string($key)) {
            throw new GameInvalidException('Game strike window is not open');
        }

        $candidate = $this->candidate($resolution, $key);
        $row = $this->apply($row, $candidate);
        $primaryKey = is_string($resolution['primaryKey'] ?? null) ? $resolution['primaryKey'] : null;

        return [
            'row' => $row,
            'choice' => $this->choiceOf($row, $candidate),
            'resolved' => $this->resolved($candidate),
            'cover' => $primaryKey === null
                ? null
                : $this->cover->info($resolution['candidates'], $primaryKey, $key),
        ];
    }

    /**
     * Готовая защита прикрывающего для применения без переброса.
     *
     * @param array<string, mixed> $candidate Кандидат.
     *
     * @return array<string, mixed> Защита.
     */
    public function resolved(array $candidate): array
    {
        $choice = is_array($candidate['choice'] ?? null) ? $candidate['choice'] : [];

        return [
            'choice' => [
                'reaction' => $choice['reaction'] ?? 'ignore',
                'blockItemInventoryId' => $choice['blockItemInventoryId'] ?? null,
                'blockItemProfileIndex' => $choice['blockItemProfileIndex'] ?? null,
                'blockItemRuleCode' => $choice['blockItemRuleCode'] ?? null,
                'turn' => false,
                'expectedSheetVersion' => is_int($candidate['expectedSheetVersion'] ?? null)
                    ? $candidate['expectedSheetVersion']
                    : 0,
            ],
            'attackerRoll' => is_array($candidate['attackerRoll'] ?? null) ? $candidate['attackerRoll'] : [],
            'defenderRoll' => is_array($candidate['defenderRoll'] ?? null) ? $candidate['defenderRoll'] : null,
            'reactionRuleCode' => is_string($candidate['reactionRuleCode'] ?? null)
                ? $candidate['reactionRuleCode']
                : null,
            'markKeys' => $this->stringList($candidate['markKeys'] ?? null),
        ];
    }

    /**
     * Переносит цель строки на прикрывающего.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $winner Победитель.
     *
     * @return array<string, mixed> Строка.
     */
    public function rewrite(array $row, array $winner): array
    {
        $actor = $winner['actor'];
        $choice = $winner['choice'];
        $row['target_type'] = $actor['type'];
        $row['target_id'] = $actor['id'];
        $row['reaction'] = $choice['reaction'] ?? null;
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'] ?? null;
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'] ?? null;
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'] ?? null;
        $row['expected_sheet_version'] = is_int($winner['expectedSheetVersion'] ?? null)
            ? $winner['expectedSheetVersion']
            : 0;

        return $row;
    }

    /**
     * Сохраняет перенесённую цель строки.
     *
     * @param int $rowId Строка.
     * @param array<string, mixed> $row Данные.
     *
     * @return void
     */
    public function persist(int $rowId, array $row): void
    {
        $this->actions->answerStrike($rowId, [
            'target_type' => $row['target_type'] ?? null,
            'target_id' => $row['target_id'] ?? null,
            'reaction' => $row['reaction'] ?? null,
            'block_item_inventory_id' => $row['block_item_inventory_id'] ?? null,
            'block_item_profile_index' => $row['block_item_profile_index'] ?? null,
            'block_item_rule_code' => $row['block_item_rule_code'] ?? null,
            'expected_sheet_version' => $row['expected_sheet_version'] ?? null,
        ]);
    }

    /**
     * Ожидания одной строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return list<array<string, mixed>> Ожидания.
     */
    private function awaitings(array $row): array
    {
        $awaitings = $this->json($row, 'resolution')['awaiting'] ?? null;
        if (!is_array($awaitings)) {
            return [];
        }

        return array_values(array_filter($awaitings, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Кандидат фактической цели по ключу.
     *
     * @param array<string, mixed> $resolution JSON решения.
     * @param string $key Ключ цели.
     *
     * @return array<string, mixed> Кандидат.
     *
     * @throws GameInvalidException Если решение окна битое.
     */
    private function candidate(array $resolution, string $key): array
    {
        $candidates = $resolution['candidates'] ?? null;
        if (!is_array($candidates) || !is_array($candidates[$key] ?? null)) {
            throw new GameInvalidException('Game strike window is not open');
        }

        $candidate = $candidates[$key];
        $actor = $candidate['actor'] ?? null;
        $choice = $candidate['choice'] ?? null;
        $attackerRoll = $candidate['attackerRoll'] ?? null;
        if (!is_array($actor)) {
            throw new GameInvalidException('Game strike window choice is invalid');
        }

        if (
            !is_string($actor['type'] ?? null)
            || !is_int($actor['id'] ?? null)
            || !is_array($choice)
            || !is_array($attackerRoll)
        ) {
            throw new GameInvalidException('Game strike window choice is invalid');
        }

        return $candidate;
    }

    /**
     * Переносит выбранного кандидата в строку.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $candidate Кандидат.
     *
     * @return array<string, mixed> Строка.
     */
    private function apply(array $row, array $candidate): array
    {
        $actor = $candidate['actor'];
        $choice = $candidate['choice'];
        $row['target_type'] = $actor['type'];
        $row['target_id'] = $actor['id'];
        $row['reaction'] = $choice['reaction'] ?? null;
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'] ?? null;
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'] ?? null;
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'] ?? null;
        $row['expected_sheet_version'] = is_int($candidate['expectedSheetVersion'] ?? null)
            ? $candidate['expectedSheetVersion']
            : 0;

        return $row;
    }

    /**
     * Защита выбранного кандидата в формате строки.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $candidate Кандидат.
     *
     * @return array<string, mixed> Защита.
     */
    private function choiceOf(array $row, array $candidate): array
    {
        $choice = $candidate['choice'];

        return [
            'reaction' => $choice['reaction'],
            'blockItemInventoryId' => $choice['blockItemInventoryId'] ?? null,
            'blockItemProfileIndex' => $choice['blockItemProfileIndex'] ?? null,
            'blockItemRuleCode' => $choice['blockItemRuleCode'] ?? null,
            'turn' => false,
            'expectedSheetVersion' => $row['expected_sheet_version'],
        ];
    }

    /**
     * JSON строки.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Данные.
     */
    private function json(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Список строк из недоверенного значения.
     *
     * @param mixed $raw Значение.
     *
     * @return array<int, string> Строки.
     */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (is_string($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
