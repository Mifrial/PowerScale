<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\CreateGameNpcInput;
use Mifrial\Roleplay\Game\Service\GameNpcHttp;

/**
 * Создание NPC.
 */
final class CreateGameNpcAction implements IActionHandler
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
     * Создаёт строку.
     *
     * @param CreateGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(CreateGameNpcInput $input): array
    {
        return $this->npcHttp->create($input);
    }
}
