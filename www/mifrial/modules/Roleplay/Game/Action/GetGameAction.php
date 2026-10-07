<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameInput;
use Mifrial\Roleplay\Game\Service\GameHttp;

/**
 * Карточка игры.
 */
final class GetGameAction implements IActionHandler
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
     * Возвращает карточку.
     *
     * @param GetGameInput $input Id.
     *
     * @return array<string, mixed> Карточка.
     */
    public function handle(GetGameInput $input): array
    {
        return $this->gameHttp->get($input);
    }
}
