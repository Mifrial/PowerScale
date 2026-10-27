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
        $defaults = $this->defaults();
        if ($raw === null) {
            if ($requireDistance) {
                throw new GameInvalidException('Game strike distance is required');
            }

            return $defaults;
        }

        if (!is_array($raw) || array_is_list($raw)) {
            throw new GameInvalidException('Game strike conditions are invalid');
        }

        $allowed = ['distance', 'cover', 'flank', 'turn'];
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new GameInvalidException('Game strike conditions are invalid');
            }
        }

        $distance = $raw['distance'] ?? null;
        if ($distance === null) {
            if ($requireDistance) {
                throw new GameInvalidException('Game strike distance is required');
            }

            $distance = $defaults['distance'];
        }

        if (!is_array($distance)) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        $distanceKeys = array_keys($distance);
        sort($distanceKeys);
        if ($distanceKeys !== ['type', 'value']) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        $type = $distance['type'] ?? null;
        $value = $distance['value'] ?? null;
        if (
            !is_string($type) || !in_array($type, ['step', 'ipari'], true)
            || !is_array($value) || !is_string($value['type'] ?? null)
        ) {
            throw new GameInvalidException('Game strike distance is invalid');
        }

        $cover = $raw['cover'] ?? $defaults['cover'];
        if (!is_array($cover)) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        $coverKeys = array_keys($cover);
        sort($coverKeys);
        if (
            $coverKeys !== ['base', 'size']
            || !is_int($cover['base'] ?? null)
            || !is_int($cover['size'] ?? null)
            || $cover['base'] < 0
        ) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        $flank = $raw['flank'] ?? false;
        $turn = $raw['turn'] ?? false;
        if (!is_bool($flank) || !is_bool($turn)) {
            throw new GameInvalidException('Game strike conditions are invalid');
        }

        return [
            'distance' => ['type' => $type, 'value' => $value],
            'cover' => ['base' => $cover['base'], 'size' => $cover['size']],
            'flank' => $flank,
            'turn' => $turn,
        ];
    }
}
