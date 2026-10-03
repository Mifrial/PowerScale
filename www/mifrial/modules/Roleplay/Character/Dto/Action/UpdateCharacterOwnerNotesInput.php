<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход character.updateOwnerNotes.
 */
final class UpdateCharacterOwnerNotesInput implements IActionInput
{
    /**
     * Собирает вход заметок.
     *
     * @param int $id Персонаж.
     * @param string $ownerNotes Текст владельца.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly string $ownerNotes,
    ) {
    }
}
