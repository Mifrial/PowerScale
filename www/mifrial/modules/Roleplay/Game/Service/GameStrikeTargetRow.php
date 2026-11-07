<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка, цель и результат одной цели удара.
 */
final class GameStrikeTargetRow
{
    /**
     * Строка удара с подменой защитника.
     *
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Строка.
     */
    public static function targetOpen(array $open, array $target): array
    {
        return array_merge($open, [
            'defender_kind' => $target['type'],
            'defender_id' => $target['id'],
        ]);
    }

    /**
     * Пара участника из массива.
     *
     * @param mixed $value Сырое значение.
     *
     * @return array{type: string, id: int}|null Пара или null.
     */
    public static function pair(mixed $value): ?array
    {
        if (!is_array($value) || !is_string($value['type'] ?? null) || !is_int($value['id'] ?? null)) {
            return null;
        }

        return ['type' => $value['type'], 'id' => $value['id']];
    }

    /**
     * Список JSON-записей строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return list<array<string, mixed>> Записи.
     */
    public static function jsonRows(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Пара защитника из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если цель в строке битая.
     */
    public static function defender(array $row): array
    {
        $kind = $row['target_type'] ?? null;
        $defenderId = $row['target_id'] ?? null;
        if (!is_string($kind) || !is_int($defenderId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return ['type' => $kind, 'id' => $defenderId];
    }

    /**
     * Условия строки. Нет JSON — дефолты.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws GameInvalidException Если JSON битый.
     */
    public static function conditions(array $row): array
    {
        return (new GameStrikeConditions())->decode($row['conditions'] ?? null);
    }

    /**
     * Запись цели.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param string|null $code Код отказа.
     * @param int|null $rating Успех.
     * @param array{base: int, size: int}|null $damage Урон.
     * @param array{base: int, size: int}|null $resistance Сопротивление.
     * @param array{base: int, size: int}|null $soak Смягчение.
     * @param array{base: int, size: int}|null $injury Повреждение.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     *
     * @return array<string, mixed> Запись.
     */
    public static function row(
        array $target,
        ?string $code,
        ?int $rating,
        ?array $damage,
        ?array $resistance,
        ?array $soak = null,
        ?array $injury = null,
        ?array $defenderRoll = null,
    ): array {
        $record = [
            'target' => $target,
            'success' => $rating,
            'damage' => $damage,
            'resistance' => $resistance,
            'injury' => $injury,
            'sheetVersion' => null,
            'code' => $code,
        ];
        if ($defenderRoll !== null) {
            $record['defenderRoll'] = $defenderRoll;
        }

        if ($soak !== null) {
            $record['S'] = $soak;
        }

        return $record;
    }
}
