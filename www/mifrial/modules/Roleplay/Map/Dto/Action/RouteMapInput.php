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
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     *
     * @return void
     */
    public function __construct(
        public readonly int $mapId,
        public readonly int $variantId,
        public readonly int $fromNodeId,
        public readonly int $toNodeId,
    ) {
    }
}
