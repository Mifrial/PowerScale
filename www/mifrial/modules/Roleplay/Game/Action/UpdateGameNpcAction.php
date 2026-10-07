<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameNpcInput;
use Mifrial\Roleplay\Game\Service\GameNpcHttp;

/**
 * Изменение NPC.
 */
final class UpdateGameNpcAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameNpcHttp $npcHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcHttp $npcHttp,
    ) {
    }

    /**
     * Пишет лист.
     *
     * @param UpdateGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка или conflicts.
     */
    public function handle(UpdateGameNpcInput $input): array
    {
        return $this->npcHttp->update($input);
    }
}
