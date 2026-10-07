<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameSessionInput;
use Mifrial\Roleplay\Game\Service\GameSessionHttp;

/**
 * Старт текущей сессии.
 */
final class StartGameSessionAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameSessionHttp $sessionHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSessionHttp $sessionHttp,
    ) {
    }

    /**
     * Запускает сессию.
     *
     * @param GameSessionInput $input Игра.
     *
     * @return array<string, mixed> Карточка.
     */
    public function handle(GameSessionInput $input): array
    {
        return $this->sessionHttp->start($input);
    }
}
