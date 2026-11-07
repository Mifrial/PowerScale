<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Убирает отказавшиеся реакции из строки удара.
 */
final class GameStrikeManeuverDecisions
{
    /**
     * Создаёт применение решений.
     *
     * @param GameStrikeManeuverRow $row Разбор строки.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeManeuverRow $row)
    {
    }

    /**
     * Применяет решения победителей к строке.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<string, mixed> Строка.
     */
    public function apply(array $row, array $targets): array
    {
        $targetKey = (is_string($row['target_type'] ?? null) ? $row['target_type'] : '')
            . ':' . (is_int($row['target_id'] ?? null) ? $row['target_id'] : 0);
        if (($targets[$targetKey]['decision'] ?? 'keep') === 'refuse') {
            $row['reaction'] = 'ignore';
        }

        $row['coverers'] = $this->coverers($row, $targets);

        return $row;
    }

    /**
     * Прикрывающие цели без отказавшихся.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array<string, mixed>> Записи.
     */
    private function coverers(array $row, array $targets): array
    {
        $coverers = [];
        foreach ($this->row->jsonRows($row, 'coverers') as $entry) {
            $actor = $this->actor($entry['actor'] ?? null);
            if ($actor === null) {
                continue;
            }

            if (($targets[$actor['type'] . ':' . $actor['id']]['decision'] ?? 'keep') === 'refuse') {
                continue;
            }

            $coverers[] = $entry;
        }

        return $coverers;
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
        if (!is_string($type) || !is_int($id)) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }
}
