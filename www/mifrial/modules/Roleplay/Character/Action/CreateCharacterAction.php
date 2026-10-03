<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\CreateCharacterInput;
use Mifrial\Roleplay\Character\Service\CharacterSave;

/**
 * Создание персонажа.
 */
final class CreateCharacterAction implements IActionHandler
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
     * @param CreateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Лист сервера.
     */
    public function handle(CreateCharacterInput $input): array
    {
        return $this->characterSave->create($input);
    }
}
