<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `map.get` и `map.archive`.
 */
final class MapIdInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $id Карта.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
    ) {
    }
}
