<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.deleteChronicleEntry.
 */
final class DeleteGameChronicleEntryInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $entryId Запись.
     *
     * @return void
     */
    public function __construct(
        public readonly int $entryId,
    ) {
    }
}
