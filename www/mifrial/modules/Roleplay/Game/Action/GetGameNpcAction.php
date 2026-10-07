<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameNpcInput;
use Mifrial\Roleplay\Game\Service\GameNpcHttp;

/**
 * Чтение NPC.
 */
final class GetGameNpcAction implements IActionHandler
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
     * Читает строку.
     *
     * @param GetGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(GetGameNpcInput $input): array
    {
        return $this->npcHttp->get($input);
    }
}
