<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.approveCharacter.
 */
final class ApproveGameCharacterInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $actualVersion Ожидаемый actual_version.
     * @param int $membershipRevision Ожидаемая revision.
     *
     * @return void
     */
    public function __construct(
        public readonly int $gameId,
        public readonly int $characterId,
        public readonly int $actualVersion,
        public readonly int $membershipRevision,
    ) {
    }
}
