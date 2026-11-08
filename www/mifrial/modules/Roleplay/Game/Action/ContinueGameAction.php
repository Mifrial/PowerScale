<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameTurnInput;
use Mifrial\Roleplay\Game\Service\GameTurnHttp;

/**
 * Доплата долга на своём ходе.
 */
final class ContinueGameAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameTurnHttp $turns Сценарий хода.
     *
     * @return void
     */
    public function __construct(private readonly GameTurnHttp $turns)
    {
    }

    /**
     * Доплачивает долг на своём ходе.
     *
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(GameTurnInput $input): array
    {
        return $this->turns->continueAction($input);
    }
}
