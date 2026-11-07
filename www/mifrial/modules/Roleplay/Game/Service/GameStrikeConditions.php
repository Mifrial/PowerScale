<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Условия удара на одну цель.
 */
final class GameStrikeConditions
{
    /**
     * Условия по умолчанию.
     *
     * @return array<string, mixed> Условия.
     */
    public function defaults(): array
    {
        return [
            'distance' => [
                'type' => 'ipari',
                'value' => ['type' => 'fixed', 'value' => 0],
            ],
            'cover' => ['base' => 0, 'size' => 0],
            'flank' => false,
            'turn' => false,
        ];
    }

    /**
     * Разбирает условия из тела запроса.
     *
     * @param mixed $raw Сырое значение.
     * @param bool $requireDistance Требовать явную дистанцию.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(mixed $raw, bool $requireDistance = false): array
    {
        try {
            return $this->normalize($raw, $requireDistance);
        } catch (GameInvalidException $exception) {
            throw new ActionException('INVALID_PARAMS', 'Invalid parameter: conditions', $exception);
        }
    }

    /**
     * Условия из строки удара. Нет JSON — дефолты.
     *
     * @param mixed $raw Сырое значение.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws GameInvalidException Если JSON битый.
     */
    public function decode(mixed $raw): array
    {
        return $this->normalize($raw, false);
    }

    /**
     * Помечает поворот и снимает фланг.
     *
     * @param array<string, mixed> $conditions Условия.
     * @param bool $turn Поворот.
     *
     * @return array<string, mixed> Условия.
     */
    public function withTurn(array $conditions, bool $turn): array
    {
        $conditions['turn'] = $turn;
        if ($turn) {
            $conditions['flank'] = false;
        }

        return $conditions;
    }

    /**
     * Нормализует и проверяет форму.
     *
     * @param mixed $raw Сырое значение.
     * @param bool $requireDistance Требовать явную дистанцию.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function normalize(mixed $raw, bool $requireDistance): array
    {
        if ($raw === null) {
            if ($requireDistance) {
                throw new GameInvalidException('Game strike distance is required');
            }

            return $this->defaults();
        }

        if (!is_array($raw) || array_is_list($raw)) {
            throw new GameInvalidException('Game strike conditions are invalid');
        }

        $this->assertKeys($raw);
        $distance = $this->distance($raw['distance'] ?? null, $requireDistance);
        $cover = $this->cover($raw['cover'] ?? null);
        [$flank, $turn] = $this->flags($raw);

        return [
            'distance' => $distance,
            'cover' => $cover,
            'flank' => $flank,
            'turn' => $turn,
        ];
    }

    /**
     * Только известные ключи условий.
     *
     * @param array<string, mixed> $raw Сырые условия.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function assertKeys(array $raw): void
    {
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, ['distance', 'cover', 'flank', 'turn'], true)) {
                throw new GameInvalidException('Game strike conditions are invalid');
            }
        }
    }

    /**
     * Флаги поворота и фланга.
     *
     * @param array<string, mixed> $raw Сырые условия.
     *
     * @return array{0: bool, 1: bool} Фланг и поворот.
     *
     * @throws GameInvalidException Если флаг не bool.
     */
    private function flags(array $raw): array
    {
        $flank = $raw['flank'] ?? false;
        $turn = $raw['turn'] ?? false;
        if (!is_bool($flank) || !is_bool($turn)) {
            throw new GameInvalidException('Game strike conditions are invalid');
        }

        return [$flank, $turn];
    }

    /**
     * Дистанция в форме ipari или step.
     *
     * @param mixed $distance Сырая дистанция.
     * @param bool $requireDistance Требовать явную дистанцию.
     *
     * @return array<string, mixed> Дистанция.
     *
     * @throws GameInvalidException Если дистанция битая.
     */
    private function distance(mixed $distance, bool $requireDistance): array
    {
        if ($distance === null) {
            if ($requireDistance) {
                throw new GameInvalidException('Game strike distance is required');
            }

            return $this->defaults()['distance'];
        }

        return $this->distanceValues($distance);
    }

    /**
     * Дистанция из формы type/value.
     *
     * @param mixed $distance Сырая дистанция.
     *
     * @return array<string, mixed> Дистанция.
     *
     * @throws GameInvalidException Если дистанция битая.
     */
    private function distanceValues(mixed $distance): array
    {
        if (!is_array($distance)) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        $keys = array_keys($distance);
        sort($keys);
        if ($keys !== ['type', 'value']) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        $type = $distance['type'] ?? null;
        $value = $distance['value'] ?? null;
        if (!is_string($type) || !in_array($type, ['step', 'ipari'], true)) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        if (!is_array($value) || !is_string($value['type'] ?? null)) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        return ['type' => $type, 'value' => $value];
    }

    /**
     * Укрытие из пары base/size.
     *
     * @param mixed $cover Сырое укрытие.
     *
     * @return array{base: int, size: int} Укрытие.
     *
     * @throws GameInvalidException Если укрытие битое.
     */
    private function cover(mixed $cover): array
    {
        $shape = $this->coverShape($cover);
        $base = $shape['base'] ?? null;
        $size = $shape['size'] ?? null;
        if (!is_int($base) || !is_int($size) || $base < 0) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Форма укрытия без проверки значений.
     *
     * @param mixed $cover Сырое укрытие.
     *
     * @return array<string, mixed> Форма.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function coverShape(mixed $cover): array
    {
        if ($cover === null) {
            return $this->defaults()['cover'];
        }

        if (!is_array($cover)) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        $keys = array_keys($cover);
        sort($keys);
        if ($keys !== ['base', 'size']) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        return $cover;
    }
}
