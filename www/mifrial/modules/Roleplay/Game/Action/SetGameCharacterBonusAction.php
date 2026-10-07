<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Roleplay\Game\Dto\Action\SetGameCharacterBonusInput;
use Mifrial\Roleplay\Game\Service\GameCharacterHttp;

/**
 * Бонус персонажа в игре.
 */
final class SetGameCharacterBonusAction implements IActionHandler
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
     * Пишет бонус.
     *
     * @param SetGameCharacterBonusInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     */
    public function handle(SetGameCharacterBonusInput $input): array
    {
        return $this->gameCharacterHttp->setBonus($input);
    }
}
