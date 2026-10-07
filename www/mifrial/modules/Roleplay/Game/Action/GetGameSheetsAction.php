<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameSheetsInput;
use Mifrial\Roleplay\Game\Service\GameProjectionHttp;

/**
 * Полный лист по ключам.
 */
final class GetGameSheetsAction implements IActionHandler
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
     * Читает листы.
     *
     * @param GetGameSheetsInput $input JSON.
     *
     * @return array<string, mixed> Листы.
     */
    public function handle(GetGameSheetsInput $input): array
    {
        return $this->projectionHttp->sheets($input);
    }
}
