<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Разбор JSON-полей и акторов строки манёвра.
 */
final class GameStrikeManeuverRow
{
    /**
     * Манёвр из строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Описание или пустой список.
     */
    public function maneuverOf(array $row): array
    {
        $raw = $this->json($row, 'maneuver');

        return is_array($raw) && !array_is_list($raw) ? $raw : [];
    }

    /**
     * Резолюция строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Резолюция.
     */
    public function resolutionOf(array $row): array
    {
        $raw = $this->json($row, 'resolution');

        return is_array($raw) ? $raw : [];
    }

    /**
     * Список JSON-записей строки.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Колонка.
     *
     * @return array<int, array<string, mixed>> Записи.
     */
    public function jsonRows(array $row, string $key): array
    {
        $raw = $this->json($row, $key);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Пара актора.
     *
     * @param mixed $raw Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function actorOf(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        $type = $raw['type'] ?? null;
        $id = $raw['id'] ?? null;
        if (!is_string($type) || !is_int($id)) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        if (!in_array($type, ['character', 'npc'], true) || $id < 1) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Значение колонки, разобранное как JSON.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Колонка.
     *
     * @return mixed Значение.
     */
    private function json(array $row, string $key): mixed
    {
        $raw = $row[$key] ?? null;

        return is_string($raw) ? json_decode($raw, true) : $raw;
    }
}
