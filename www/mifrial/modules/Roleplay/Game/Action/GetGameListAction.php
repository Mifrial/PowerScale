<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Список игр актора.
 */
final class GetGameListAction implements IActionHandler
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
     * Возвращает карточки.
     *
     * @return list<array<string, mixed>> Список.
     */
    public function handle(): array
    {
        return $this->gameHttp->getList();
    }
}
