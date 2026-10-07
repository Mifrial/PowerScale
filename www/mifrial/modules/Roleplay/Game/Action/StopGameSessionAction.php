<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameSessionInput;
use Mifrial\Roleplay\Game\Service\GameSessionHttp;

/**
 * Остановка текущей сессии.
 */
final class StopGameSessionAction implements IActionHandler
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
     * Останавливает сессию.
     *
     * @param GameSessionInput $input Игра.
     *
     * @return array<string, mixed> Карточка.
     */
    public function handle(GameSessionInput $input): array
    {
        return $this->sessionHttp->stop($input);
    }
}
