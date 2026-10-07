<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Обновление игры.
 */
final class UpdateGameAction implements IActionHandler
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
     * Пишет изменяемые поля.
     *
     * @param UpdateGameInput $input JSON.
     *
     * @return array<string, mixed> Карточка.
     */
    public function handle(UpdateGameInput $input): array
    {
        return $this->gameHttp->update($input);
    }
}
