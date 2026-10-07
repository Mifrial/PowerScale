<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameBattleSheetsInput;
use Mifrial\Roleplay\Game\Service\GameProjectionHttp;

/**
 * Листы состава одного боя.
 */
final class GetGameBattleSheetsAction implements IActionHandler
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
     * Читает состав.
     *
     * @param GetGameBattleSheetsInput $input JSON.
     *
     * @return array<string, mixed> Состав.
     */
    public function handle(GetGameBattleSheetsInput $input): array
    {
        return $this->projectionHttp->battleSheets($input);
    }
}
