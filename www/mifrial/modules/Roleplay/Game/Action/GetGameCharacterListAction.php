<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\GetGameCharacterListInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Список персонажей игры.
 */
final class GetGameCharacterListAction implements IActionHandler
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
     * Отдаёт строки.
     *
     * @param GetGameCharacterListInput $input JSON.
     *
     * @return list<array<string, mixed>> Строки.
     */
    public function handle(GetGameCharacterListInput $input): array
    {
        return $this->gameCharacterHttp->getList($input);
    }
}
