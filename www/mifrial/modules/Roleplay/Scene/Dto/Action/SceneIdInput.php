<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `scene.get` и `scene.archive`.
 */
final class SceneIdInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $id Локация.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
    ) {
    }
}
