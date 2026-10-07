<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\ApproveGameCharacterInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Approve строки персонажа.
 */
final class ApproveGameCharacterAction implements IActionHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param GameCharacterHttp $gameCharacterHttp Сценарий.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCharacterHttp $gameCharacterHttp,
    ) {
    }

    /**
     * Пишет snapshot.
     *
     * @param ApproveGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(ApproveGameCharacterInput $input): array
    {
        return $this->gameCharacterHttp->approve($input);
    }
}
