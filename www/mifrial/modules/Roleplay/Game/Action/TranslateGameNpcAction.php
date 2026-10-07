<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\TranslateGameNpcInput;
use Mifrial\Roleplay\Game\Service\GameNpcHttp;

/**
 * Перевод NPC на ревизию игры.
 */
final class TranslateGameNpcAction implements IActionHandler
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
     * Переводит лист.
     *
     * @param TranslateGameNpcInput $input JSON.
     *
     * @return array<string, mixed> Строка или conflicts.
     */
    public function handle(TranslateGameNpcInput $input): array
    {
        return $this->npcHttp->translate($input);
    }
}
