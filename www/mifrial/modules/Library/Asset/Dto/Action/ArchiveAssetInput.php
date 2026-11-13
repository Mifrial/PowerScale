<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `asset.archive`.
 */
final class ArchiveAssetInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $id Ассет.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
    ) {
    }
}
