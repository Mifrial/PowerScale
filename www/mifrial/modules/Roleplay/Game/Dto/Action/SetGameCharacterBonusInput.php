<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.setCharacterBonus.
 */
final class SetGameCharacterBonusInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $membershipRevision Ожидаемая revision.
     * @param int $osBonus Бонус ОС.
     * @param int $orBonus Бонус ОР.
     * @param int $olBonus Бонус ОЛ.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $characterId,
        public readonly int $membershipRevision,
        public readonly int $osBonus,
        public readonly int $orBonus,
        public readonly int $olBonus,
    ) {
    }
}
