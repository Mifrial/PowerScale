<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GameManualSheetInput;
use Mifrial\Roleplay\Game\Service\GameManualSheetHttp;

/**
 * Ручная правка ресурса, состояния или инвентаря.
 */
final class GameManualSheetAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameManualSheetHttp $manualSheetHttp Сценарий.
     *
     * @return void
     */
    public function __construct(private readonly GameManualSheetHttp $manualSheetHttp)
    {
    }

    /**
     * Выполняет команду.
     *
     * @param GameManualSheetInput $input JSON.
     *
     * @return array<string, mixed> Итог.
     */
    public function handle(GameManualSheetInput $input): array
    {
        return $this->manualSheetHttp->act($input);
    }
}
