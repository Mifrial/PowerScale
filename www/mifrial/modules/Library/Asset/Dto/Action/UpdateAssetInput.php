<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `asset.update`.
 */
final class UpdateAssetInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $id Ассет.
     * @param string $name Новая подпись.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
