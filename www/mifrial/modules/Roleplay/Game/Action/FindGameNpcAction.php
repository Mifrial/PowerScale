<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\FindGameNpcInput;
use Mifrial\Roleplay\Game\Service\GameNpcHttp;

/**
 * Поиск NPC по одному id: игра выводится из строки.
 */
final class FindGameNpcAction implements IActionHandler
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
     * Находит строку.
     *
     * @param FindGameNpcInput $input JSON.
     *
     * @return array{id: int, gameId: int, name: string} Ссылка на NPC.
     */
    public function handle(FindGameNpcInput $input): array
    {
        return $this->npcHttp->find($input);
    }
}
