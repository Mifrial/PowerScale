<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Character\Service\CharacterRead;

/**
 * Список видимых персонажей.
 */
final class GetCharacterListAction implements IActionHandler
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
     * Возвращает проекции.
     *
     * @return list<array<string, mixed>> Список.
     */
    public function handle(): array
    {
        return $this->characterRead->getList();
    }
}
