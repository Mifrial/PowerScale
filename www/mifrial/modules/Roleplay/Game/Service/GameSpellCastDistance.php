<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Дистанция заявки каста: размерная форма `{type: 'ipari', value}` и старое целое.
 */
final class GameSpellCastDistance
{
    /**
     * Точная дистанция.
     *
     * Канон — размерная форма, как у условий удара: `distance` с `type: ipari`.
     * Старый `distanceIpari` принимается для совместимости; если пришли оба
     * значения, они должны означать одну дистанцию.
     *
     * @param array<string, mixed> $holder Контекст каста или цель прыжка.
     *
     * @return DimensionalNumber Дистанция.
     *
     * @throws GameInvalidException Если форма чужая или числа расходятся.
     */
    public function value(array $holder): DimensionalNumber
    {
        $legacy = $holder['distanceIpari'] ?? null;
        if ($legacy !== null && (!is_int($legacy) || $legacy < 0)) {
            throw $this->invalid();
        }

        if (!array_key_exists('distance', $holder) || $holder['distance'] === null) {
            return new DimensionalNumber($legacy ?? 0, 0);
        }

        $distance = $this->document($holder['distance']);
        if ($legacy !== null && $legacy !== $distance->toInteger()) {
            throw $this->invalid();
        }

        return $distance;
    }

    /**
     * Точная дистанция целым числом ипари.
     *
     * @param array<string, mixed> $holder Контекст каста или цель прыжка.
     *
     * @return int Ипари.
     *
     * @throws GameInvalidException Если форма чужая или числа расходятся.
     */
    public function ipari(array $holder): int
    {
        return $this->value($holder)->toInteger();
    }

    /**
     * Разбор размерной дистанции `{type: 'ipari', value: формула}`.
     *
     * @param mixed $distance Сырое значение.
     *
     * @return DimensionalNumber Дистанция.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function document(mixed $distance): DimensionalNumber
    {
        $row = $this->row($distance, ['type', 'value']);
        if (($row['type'] ?? null) !== 'ipari') {
            throw $this->invalid();
        }

        $formula = $row['value'] ?? null;
        $node = is_array($formula) && !array_is_list($formula) ? ($formula['type'] ?? null) : null;

        return match ($node) {
            'literal' => $this->pair($formula['value'] ?? null, []),
            'dimensional' => $this->pair($formula, ['type']),
            'fixed' => $this->fixed($formula['value'] ?? null),
            default => throw $this->invalid(),
        };
    }

    /**
     * Пара база и размер с точным набором ключей.
     *
     * @param mixed $value Значение.
     * @param array<int, string> $extra Дополнительные допустимые ключи.
     *
     * @return DimensionalNumber Число.
     *
     * @throws GameInvalidException Если форма чужая или база отрицательна.
     */
    private function pair(mixed $value, array $extra): DimensionalNumber
    {
        $row = $this->row($value, [...$extra, 'base', 'size']);
        $base = $row['base'] ?? null;
        $size = $row['size'] ?? null;
        if (!is_int($base) || !is_int($size) || $base < 0) {
            throw $this->invalid();
        }

        return new DimensionalNumber($base, $size);
    }

    /**
     * Целое расстояние без размера.
     *
     * @param mixed $value Значение.
     *
     * @return DimensionalNumber Число.
     *
     * @throws GameInvalidException Если значение чужое или отрицательное.
     */
    private function fixed(mixed $value): DimensionalNumber
    {
        if (!is_int($value) || $value < 0) {
            throw $this->invalid();
        }

        return new DimensionalNumber($value, 0);
    }

    /**
     * Объект ровно с перечисленными ключами.
     *
     * @param mixed $value Значение.
     * @param array<int, string> $keys Ключи.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function row(mixed $value, array $keys): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw $this->invalid();
        }

        $actual = array_keys($value);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw $this->invalid();
        }

        return $value;
    }

    /**
     * Ошибка формы дистанции.
     *
     * @return GameInvalidException Исключение.
     */
    private function invalid(): GameInvalidException
    {
        return new GameInvalidException('Game spell distance is invalid');
    }
}
