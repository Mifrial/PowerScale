<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\RejectGameCharacterInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Reject заявки.
 */
final class RejectGameCharacterAction implements IActionHandler
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
     * Удаляет submitted.
     *
     * @param RejectGameCharacterInput $input JSON.
     *
     * @return null Пусто.
     */
    public function handle(RejectGameCharacterInput $input): null
    {
        return $this->gameCharacterHttp->reject($input);
    }
}
