<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\SubmitGameCharacterInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Подача персонажа в игру.
 */
final class SubmitGameCharacterAction implements IActionHandler
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
     * Пишет заявку.
     *
     * @param SubmitGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(SubmitGameCharacterInput $input): array
    {
        return $this->gameCharacterHttp->submit($input);
    }
}
