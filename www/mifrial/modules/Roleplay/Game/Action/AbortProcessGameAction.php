<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameTurnInput;
use Mifrial\Roleplay\Game\Service\GameTurnHttp;

/**
 * Прерывание процесса на своём ходе.
 */
final class AbortProcessGameAction implements IActionHandler
{
    public function __construct(private readonly GameTurnHttp $turns)
    {
    }

    /**
     * @param GameTurnInput $input Вход.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(GameTurnInput $input): array
    {
        return $this->turns->abortProcess($input);
    }
}
