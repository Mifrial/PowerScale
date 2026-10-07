<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ReturnGameCharacterInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Return строки персонажа.
 */
final class ReturnGameCharacterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCharacterHttp $gameCharacterHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCharacterHttp $gameCharacterHttp,
    ) {
    }

    /**
     * Пишет причину.
     *
     * @param ReturnGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(ReturnGameCharacterInput $input): array
    {
        return $this->gameCharacterHttp->returnToOwner($input);
    }
}
