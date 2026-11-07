<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * Допустимые значения визуального декора области.
 *
 * Набор задаёт, какие символы рассыпаются по полигону. Декор рисуется только
 * на клиенте и не участвует в маршруте.
 */
final class MapDecor
{
    /**
     * Наборы символов.
     */
    public const SETS = ['grass', 'tree', 'pine', 'field', 'meadow', 'mixed'];

    /**
     * Максимальная плотность: символов на 10 000 px².
     */
    public const DENSITY_MAX = 200;

    /**
     * Минимальный масштаб символа, %.
     */
    public const SCALE_MIN = 40;

    /**
     * Максимальный масштаб символа, %.
     */
    public const SCALE_MAX = 250;

    /**
     * Максимальный разброс позиции, %.
     */
    public const JITTER_MAX = 100;

    /**
     * Максимальное зерно раскладки.
     */
    public const SEED_MAX = 1000000;
}
