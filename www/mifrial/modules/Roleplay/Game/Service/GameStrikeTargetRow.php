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
     * Пара защитника из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
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
