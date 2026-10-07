<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameRosterInput;
use Mifrial\Roleplay\Game\Service\GameProjectionHttp;

/**
 * Краткий roster игры.
 */
final class GetGameRosterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameProjectionHttp $projectionHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameProjectionHttp $projectionHttp,
    ) {
    }

    /**
     * Читает roster.
     *
     * @param GetGameRosterInput $input JSON.
     *
     * @return array<string, mixed> Записи.
     */
    public function handle(GetGameRosterInput $input): array
    {
        return $this->projectionHttp->roster($input);
    }
}
