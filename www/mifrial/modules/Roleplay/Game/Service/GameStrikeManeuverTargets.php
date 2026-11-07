<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Собирает защитников, которые отреагировали на манёвр.
 */
final class GameStrikeManeuverTargets
{
    /**
     * Создаёт сборщика защитников.
     *
     * @param GameStrikeManeuverRow $row Разбор строки.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeManeuverRow $row)
    {
    }

    /**
     * Собирает защитников, которые отреагировали.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Защита цели.
     *
     * @return array<string, array<string, mixed>> Защитники по ключу.
     */
    public function collect(array $row, array $choice): array
    {
        $options = $this->options($row);
        $targets = [];
        $primary = $this->primary($row, $choice, $options);
        if ($primary !== null) {
            $targets[$this->key($primary['actor'])] = $primary;
        }

        foreach ($this->row->jsonRows($row, 'coverers') as $entry) {
            $coverer = $this->coverer($entry, $options);
            if ($coverer !== null) {
                $targets[$this->key($coverer['actor'])] = $coverer;
            }
        }

        return $targets;
    }

    /**
     * Исходная цель удара.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Защита цели.
     * @param array<int, mixed> $options Разрешённые пары.
     *
     * @return array<string, mixed>|null Защитник.
     */
    private function primary(array $row, array $choice, array $options): ?array
    {
        $target = [
            'type' => is_string($row['target_type'] ?? null) ? $row['target_type'] : '',
            'id' => is_int($row['target_id'] ?? null) ? $row['target_id'] : 0,
        ];
        $reaction = $choice['reaction'] ?? 'ignore';
        if (!in_array($target['type'], ['character', 'npc'], true) || $target['id'] <= 0) {
            return null;
        }

        if ($reaction === 'ignore') {
            return null;
        }

        return $this->entry($target, $reaction, $choice, $options);
    }

    /**
     * Прикрывающая цель.
     *
     * @param array<string, mixed> $entry Запись прикрытия.
     * @param array<int, mixed> $options Разрешённые пары.
     *
     * @return array<string, mixed>|null Защитник.
     */
    private function coverer(array $entry, array $options): ?array
    {
        $actor = $this->actor($entry['actor'] ?? null);
        $reaction = $entry['reaction'] ?? 'ignore';
        if ($actor === null || $reaction === 'ignore') {
            return null;
        }

        return $this->entry($actor, $reaction, [
            'reaction' => $reaction,
            'blockItemInventoryId' => $entry['blockItemInventoryId'] ?? null,
            'blockItemProfileIndex' => $entry['blockItemProfileIndex'] ?? null,
            'blockItemRuleCode' => $entry['blockItemRuleCode'] ?? null,
        ], $options);
    }

    /**
     * Проверенная пара цели.
     *
     * @param mixed $raw Значение.
     *
     * @return array{type: string, id: int}|null Пара.
     */
    private function actor(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $type = $raw['type'] ?? null;
        $id = $raw['id'] ?? null;
        if (!is_string($type) || !is_int($id) || !in_array($type, ['character', 'npc'], true)) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Запись защитника.
     *
     * @param array{type: string, id: int} $actor Цель.
     * @param mixed $reaction Реакция.
     * @param array<string, mixed> $choice Защита.
     * @param array<int, mixed> $options Разрешённые пары.
     *
     * @return array<string, mixed> Защитник.
     */
    private function entry(array $actor, mixed $reaction, array $choice, array $options): array
    {
        return [
            'actor' => $actor,
            'reaction' => $reaction,
            'choice' => $choice,
            'options' => $options,
            'check' => null,
            'roll' => null,
            'won' => null,
            'decision' => null,
        ];
    }

    /**
     * Ключ цели.
     *
     * @param array{type: string, id: int} $actor Цель.
     *
     * @return string Ключ.
     */
    private function key(array $actor): string
    {
        return $actor['type'] . ':' . $actor['id'];
    }

    /**
     * Разрешённые пары проверки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<int, mixed> Пары.
     */
    private function options(array $row): array
    {
        $maneuver = $this->row->maneuverOf($row);

        return is_array($maneuver['target'] ?? null) ? $maneuver['target'] : [];
    }
}
