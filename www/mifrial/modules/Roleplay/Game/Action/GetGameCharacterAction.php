<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameCharacterInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Чтение строки персонажа.
 */
final class GetGameCharacterAction implements IActionHandler
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
     * Отдаёт строку.
     *
     * @param GetGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(GetGameCharacterInput $input): array
    {
        return $this->gameCharacterHttp->get($input);
    }
}
