<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ResolveGameStrikeInput;
use Mifrial\Roleplay\Game\Service\GameStrikeHttp;

/**
 * Ответ защиты.
 */
final class ResolveGameStrikeAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameStrikeHttp $strikeHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeHttp $strikeHttp,
    ) {
    }

    /**
     * Закрывает удар.
     *
     * @param ResolveGameStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(ResolveGameStrikeInput $input): array
    {
        return $this->strikeHttp->resolveStrike($input);
    }
}
