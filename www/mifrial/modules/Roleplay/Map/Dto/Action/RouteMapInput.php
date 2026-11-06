<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `map.route`.
 */
final class RouteMapInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     * @param int $fromX Начало X.
     * @param int $fromY Начало Y.
     * @param int $toX Конец X.
     * @param int $toY Конец Y.
     *
     * @return void
     */
    public function __construct(
        public readonly int $mapId,
        public readonly int $variantId,
        public readonly int $fromX,
        public readonly int $fromY,
        public readonly int $toX,
        public readonly int $toY,
    ) {
    }
}
