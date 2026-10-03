<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Dto\Action\ValidateCharacterInput;
use Mifrial\Roleplay\Character\Service\CharacterSave;

/**
 * Проверка листа без записи.
 */
final class ValidateCharacterAction implements IActionHandler
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
     * Возвращает valid, problems и sheet.
     *
     * @param ValidateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Отчёт.
     */
    public function handle(ValidateCharacterInput $input): array
    {
        return $this->characterSave->validate($input);
    }
}
