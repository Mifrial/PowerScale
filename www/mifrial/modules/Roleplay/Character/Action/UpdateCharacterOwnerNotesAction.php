<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterOwnerNotesInput;
use Mifrial\Roleplay\Character\Service\CharacterRead;

/**
 * Заметки владельца.
 */
final class UpdateCharacterOwnerNotesAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param CharacterRead $characterRead Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRead $characterRead,
    ) {
    }

    /**
     * Пишет заметки.
     *
     * @param UpdateCharacterOwnerNotesInput $input JSON.
     *
     * @return array<string, mixed> Вид владельца.
     */
    public function handle(UpdateCharacterOwnerNotesInput $input): array
    {
        return $this->characterRead->updateOwnerNotes($input);
    }
}
