<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterInput;
use Mifrial\Roleplay\Character\Service\CharacterSave;

/**
 * Обновление персонажа.
 */
final class UpdateCharacterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param CharacterSave $characterSave Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterSave $characterSave,
    ) {
    }

    /**
     * Пишет лист.
     *
     * @param UpdateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Лист сервера.
     */
    public function handle(UpdateCharacterInput $input): array
    {
        return $this->characterSave->update($input);
    }
}
