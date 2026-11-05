<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ResolveGameStrikeWindowInput;
use Mifrial\Roleplay\Game\Service\GameStrikeWindowHttp;

/**
 * Решение окна удара: выбор фактической цели.
 */
final class ResolveGameStrikeWindowAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameStrikeWindowHttp $window Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeWindowHttp $window)
    {
    }

    /**
     * Выполняет действие.
     *
     * @param ResolveGameStrikeWindowInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(ResolveGameStrikeWindowInput $input): array
    {
        return $this->window->run($input);
    }
}
