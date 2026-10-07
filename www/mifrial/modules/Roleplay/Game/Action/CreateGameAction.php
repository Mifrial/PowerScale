<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\CreateGameInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Создание игры.
 */
final class CreateGameAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameHttp $gameHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameHttp $gameHttp,
    ) {
    }

    /**
     * Пишет строку.
     *
     * @param CreateGameInput $input JSON.
     *
     * @return array<string, mixed> Карточка.
     */
    public function handle(CreateGameInput $input): array
    {
        return $this->gameHttp->create($input);
    }
}
