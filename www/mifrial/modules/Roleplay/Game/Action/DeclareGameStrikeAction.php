<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\DeclareGameStrikeInput;
use Mifrial\Roleplay\Game\Service\GameStrikeHttp;

/**
 * Решение атаки.
 */
final class DeclareGameStrikeAction implements IActionHandler
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
     * Открывает удар.
     *
     * @param DeclareGameStrikeInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(DeclareGameStrikeInput $input): array
    {
        return $this->strikeHttp->declareStrike($input);
    }
}
