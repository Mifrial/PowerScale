<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Service\MapDecor;

/**
 * Строгий разбор визуального декора строки карты: лента и область.
 */
final class MapDecorValues
{
    /**
     * Ключи декора ленты.
     */
    private const LINE_KEYS = ['flow', 'fillOpacity', 'depth', 'depthWidth'];

    /**
     * Ключи декора области.
     */
    private const AREA_KEYS = ['fillOpacity', 'set', 'density', 'scale', 'jitter', 'seed'];

    /**
     * Визуальный декор ленты.
     *
     * @param mixed $value Значение из строки.
     *
     * @return array<string, mixed> Декор; пусто — без декора.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function decor(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException('Map line decor is invalid');
        }

        self::assertKeys($value, self::LINE_KEYS, 'Map line decor has an unknown key');
        $decor = self::lineNumbers($value);
        $flow = $value['flow'] ?? null;
        if ($flow === null) {
            return $decor;
        }

        if (!is_array($flow)) {
            throw new MapInvalidException('Map line flow is invalid');
        }

        $decor['flow'] = self::flow($flow);

        return $decor;
    }

    /**
     * Визуальный декор области.
     *
     * @param mixed $value Значение из строки.
     *
     * @return array<string, mixed> Декор; пусто — без декора.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public static function areaDecor(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException('Map area decor is invalid');
        }

        self::assertKeys($value, self::AREA_KEYS, 'Map area decor has an unknown key');
        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = self::decorInt($value['fillOpacity'], 0, 100);
        }

        $set = $value['set'] ?? null;
        if ($set === null) {
            if (count($decor) !== count($value)) {
                throw new MapInvalidException('Map area decor has settings without a set');
            }

            return $decor;
        }

        return self::areaSet($set, $value, $decor);
    }

    /**
     * Числовые поля декора ленты.
     *
     * @param array<string, mixed> $value Значение из строки.
     *
     * @return array<string, int> Поля.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private static function lineNumbers(array $value): array
    {
        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = self::decorInt($value['fillOpacity'], 0, 100);
        }

        if (array_key_exists('depth', $value)) {
            $decor['depth'] = self::decorInt($value['depth'], 0, 100);
        }

        if (array_key_exists('depthWidth', $value)) {
            $decor['depthWidth'] = self::decorInt($value['depthWidth'], 20, 100);
        }

        return $decor;
    }

    /**
     * Течение ленты.
     *
     * @param array<string, mixed> $flow Значение из строки.
     *
     * @return array{enabled: bool, speed: int, density: int, opacity: int, color: string, direction: int, seed: int} Течение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private static function flow(array $flow): array
    {
        $enabled = $flow['enabled'] ?? false;
        if (!is_bool($enabled)) {
            throw new MapInvalidException('Map line flow enabled is invalid');
        }

        $direction = self::decorInt($flow['direction'] ?? 1, -1, 1);
        if ($direction === 0) {
            throw new MapInvalidException('Map line flow direction is invalid');
        }

        $color = $flow['color'] ?? '#ffffff';
        if (!is_string($color) || preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new MapInvalidException('Map line flow color is invalid');
        }

        return [
            'enabled' => $enabled,
            'speed' => self::decorInt($flow['speed'] ?? 40, 1, 600),
            'density' => self::decorInt($flow['density'] ?? 50, 0, 100),
            'opacity' => self::decorInt($flow['opacity'] ?? 18, 0, 100),
            'color' => $color,
            'direction' => $direction,
            'seed' => self::decorInt($flow['seed'] ?? 0, 0, 1000000),
        ];
    }

    /**
     * Набор декора области с его полями.
     *
     * @param mixed $set Значение набора.
     * @param array<string, mixed> $value Значение из строки.
     * @param array<string, mixed> $decor Уже собранный декор.
     *
     * @return array<string, mixed> Декор.
     *
     * @throws MapInvalidException Если набор или поля неверны.
     */
    private static function areaSet(mixed $set, array $value, array $decor): array
    {
        if (!is_string($set) || !in_array($set, MapDecor::SETS, true)) {
            throw new MapInvalidException('Map area decor set is invalid');
        }

        $decor['set'] = $set;
        $ranges = [
            'density' => [0, MapDecor::DENSITY_MAX],
            'scale' => [MapDecor::SCALE_MIN, MapDecor::SCALE_MAX],
            'jitter' => [0, MapDecor::JITTER_MAX],
            'seed' => [0, MapDecor::SEED_MAX],
        ];
        foreach ($ranges as $key => [$min, $max]) {
            if (array_key_exists($key, $value)) {
                $decor[$key] = self::decorInt($value[$key], $min, $max);
            }
        }

        return $decor;
    }

    /**
     * Проверяет, что в декоре нет неизвестных ключей.
     *
     * @param array<string, mixed> $value Значение из строки.
     * @param array<int, string> $allowed Допустимые ключи.
     * @param string $message Текст ошибки.
     *
     * @return void
     *
     * @throws MapInvalidException Если ключ неизвестен.
     */
    private static function assertKeys(array $value, array $allowed, string $message): void
    {
        foreach (array_keys($value) as $unknown) {
            if (!in_array($unknown, $allowed, true)) {
                throw new MapInvalidException($message);
            }
        }
    }

    /**
     * Целое декора в диапазоне.
     *
     * @param mixed $value Значение.
     * @param int $min Минимум.
     * @param int $max Максимум.
     *
     * @return int Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private static function decorInt(mixed $value, int $min, int $max): int
    {
        $int = MapValues::intValue($value);
        if ($int === null || $int < $min || $int > $max) {
            throw new MapInvalidException('Map line flow value is invalid');
        }

        return $int;
    }
}
