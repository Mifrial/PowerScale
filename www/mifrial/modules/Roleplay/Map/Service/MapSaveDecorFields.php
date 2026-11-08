<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Разбор и проверка визуального декора в снимке карты.
 */
final class MapSaveDecorFields
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
     * Ключи течения.
     */
    private const FLOW_KEYS = ['enabled', 'speed', 'density', 'opacity', 'color', 'direction', 'seed'];

    /**
     * Создаёт разбор.
     *
     * @param MapSaveFields $fields Скалярные поля.
     *
     * @return void
     */
    public function __construct(
        private readonly MapSaveFields $fields,
    ) {
    }

    /**
     * Декор ленты: пусто или спокойное течение.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array{flow?: array{enabled: bool, speed: int, density: int, opacity: int,
     *     color: string, direction: int, seed: int}, fillOpacity?: int, depth?: int,
     *     depthWidth?: int} Декор.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function decorValue(array $row, string $key, string $path): array
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException($path . ' is invalid');
        }

        $this->assertKeys($value, self::LINE_KEYS, $path);
        $decor = $this->lineNumbers($value, $path);
        $flow = $value['flow'] ?? null;
        if ($flow === null) {
            return $decor;
        }

        if (!is_array($flow)) {
            throw new MapInvalidException($path . '.flow is invalid');
        }

        $decor['flow'] = $this->flow($flow, $path . '.flow');

        return $decor;
    }

    /**
     * Декор области: плотность заливки и набор.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return array{fillOpacity?: int, set?: string, density?: int, scale?: int,
     *     jitter?: int, seed?: int} Декор.
     *
     * @throws MapInvalidException Если форма неверна.
     */
    public function areaDecorValue(array $row, string $key, string $path): array
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            throw new MapInvalidException($path . ' is invalid');
        }

        $this->assertKeys($value, self::AREA_KEYS, $path);
        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = $this->fields->intValue($value, 'fillOpacity', $path . '.fillOpacity', 0, 100);
        }

        $set = $value['set'] ?? null;
        if ($set === null) {
            if (count($decor) !== count($value)) {
                throw new MapInvalidException($path . ' has settings without a set');
            }

            return $decor;
        }

        return $this->areaSet($set, $value, $decor, $path);
    }

    /**
     * Числовые поля декора ленты.
     *
     * @param array<string, mixed> $value Значение.
     * @param string $path Путь.
     *
     * @return array{fillOpacity?: int, depth?: int, depthWidth?: int} Поля.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function lineNumbers(array $value, string $path): array
    {
        $decor = [];
        if (array_key_exists('fillOpacity', $value)) {
            $decor['fillOpacity'] = $this->fields->intValue($value, 'fillOpacity', $path . '.fillOpacity', 0, 100);
        }

        if (array_key_exists('depth', $value)) {
            $decor['depth'] = $this->fields->intValue($value, 'depth', $path . '.depth', 0, 100);
        }

        if (array_key_exists('depthWidth', $value)) {
            $decor['depthWidth'] = $this->fields->intValue($value, 'depthWidth', $path . '.depthWidth', 20, 100);
        }

        return $decor;
    }

    /**
     * Течение ленты.
     *
     * @param array<string, mixed> $flow Значение.
     * @param string $path Путь.
     *
     * @return array{enabled: bool, speed: int, density: int, opacity: int, color: string,
     *     direction: int, seed: int} Течение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function flow(array $flow, string $path): array
    {
        $this->assertKeys($flow, self::FLOW_KEYS, $path);
        $direction = $this->flowInt($flow, 'direction', $path . '.direction', -1, 1, 1);
        if ($direction === 0) {
            throw new MapInvalidException($path . '.direction is invalid');
        }

        return [
            'enabled' => $this->flowBool($flow, 'enabled', $path . '.enabled'),
            'speed' => $this->flowInt($flow, 'speed', $path . '.speed', 1, 600, 40),
            'density' => $this->flowInt($flow, 'density', $path . '.density', 0, 100, 50),
            'opacity' => $this->flowInt($flow, 'opacity', $path . '.opacity', 0, 100, 18),
            'color' => array_key_exists('color', $flow)
                ? $this->fields->colorValue($flow, 'color', $path . '.color')
                : '#ffffff',
            'direction' => $direction,
            'seed' => $this->flowInt($flow, 'seed', $path . '.seed', 0, 1000000, 0),
        ];
    }

    /**
     * Набор декора области с его полями.
     *
     * @param mixed $set Значение набора.
     * @param array<string, mixed> $value Значение.
     * @param array<string, mixed> $decor Уже собранный декор.
     * @param string $path Путь.
     *
     * @return array<string, mixed> Декор.
     *
     * @throws MapInvalidException Если набор или поля неверны.
     */
    private function areaSet(mixed $set, array $value, array $decor, string $path): array
    {
        if (!is_string($set) || !in_array($set, MapDecor::SETS, true)) {
            throw new MapInvalidException($path . '.set is invalid');
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
                $decor[$key] = $this->fields->intValue($value, $key, $path . '.' . $key, $min, $max);
            }
        }

        return $decor;
    }

    /**
     * Проверяет, что в декоре нет неизвестных ключей.
     *
     * @param array<string, mixed> $value Значение.
     * @param array<int, string> $allowed Допустимые ключи.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws MapInvalidException Если ключ неизвестен.
     */
    private function assertKeys(array $value, array $allowed, string $path): void
    {
        foreach (array_keys($value) as $unknown) {
            if (!in_array($unknown, $allowed, true)) {
                throw new MapInvalidException($path . ' has an unknown key');
            }
        }
    }

    /**
     * Целое поле течения с умолчанием.
     *
     * @param array<string|int, mixed> $flow Течение.
     * @param string $key Ключ.
     * @param string $path Путь.
     * @param int $min Минимум.
     * @param int $max Максимум.
     * @param int $default Умолчание.
     *
     * @return int Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function flowInt(array $flow, string $key, string $path, int $min, int $max, int $default): int
    {
        if (!array_key_exists($key, $flow)) {
            return $default;
        }

        return $this->fields->intValue($flow, $key, $path, $min, $max);
    }

    /**
     * Логическое поле течения с умолчанием.
     *
     * @param array<string|int, mixed> $flow Течение.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return bool Значение.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function flowBool(array $flow, string $key, string $path): bool
    {
        if (!array_key_exists($key, $flow)) {
            return false;
        }

        return $this->fields->boolValue($flow, $key, $path);
    }
}
