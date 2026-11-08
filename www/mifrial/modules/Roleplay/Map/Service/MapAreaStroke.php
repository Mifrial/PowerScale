<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

/**
 * Шкала границы области.
 *
 * Цвет хранится отдельно от заливки: пустая строка — границы нет,
 * иначе `#rrggbb`. Толщина задаётся в экранных пикселях.
 */
final class MapAreaStroke
{
    /**
     * Толщина по умолчанию.
     */
    public const DEFAULT_WIDTH = 2;

    /**
     * Минимальная толщина.
     */
    public const MIN_WIDTH = 1;

    /**
     * Максимальная толщина.
     */
    public const MAX_WIDTH = 8;
}
