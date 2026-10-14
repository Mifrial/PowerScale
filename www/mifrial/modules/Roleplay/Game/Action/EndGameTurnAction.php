<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameTurnInput;
use Mifrial\Roleplay\Game\Service\GameTurnHttp;

/**
 * Конец хода.
 */
final class EndGameTurnAction implements IActionHandler
{
    public function __construct(private readonly GameTurnHttp $turns)
    {
    }

    /**
     * @return array<string, mixed> Итог.
     */
    public function handle(GameTurnInput $input): array
    {
        return $this->turns->end($input);
    }
}
