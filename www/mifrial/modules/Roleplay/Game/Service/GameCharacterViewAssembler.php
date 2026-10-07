<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Плоский JSON строки персонажа. Имён и прав нет.
 */
final class GameCharacterViewAssembler
{
    /**
     * Строка с reviewState.
     *
     * @param GameCharacterRecord $record Строка после чтения actual.
     *
     * @return array<string, mixed> JSON.
     *
     * @throws GameInvalidException Если reviewState ещё нет.
     */
    public function detail(GameCharacterRecord $record): array
    {
        $reviewState = $record->getReviewState();
        $needsModeration = $record->needsModeration();
        $canStartSession = $record->canStartSession();
        $isActiveSessionParticipant = $record->isActiveSessionParticipant();
        $this->assertReviewed($reviewState, $needsModeration, $canStartSession, $isActiveSessionParticipant);

        return [
            'gameId' => $record->getGameId(),
            'characterId' => $record->getCharacterId(),
            'characterOwnerId' => $record->getCharacterOwnerId(),
            'status' => $record->getStatus(),
            'membershipRevision' => $record->getMembershipRevision(),
            'reviewState' => $reviewState,
            'needsModeration' => $needsModeration,
            'canStartSession' => $canStartSession,
            'isActiveSessionParticipant' => $isActiveSessionParticipant,
            'returnedAt' => $record->getReturnedAt()?->toUnix(),
            'returnReason' => $record->getReturnReason(),
            'returnMessageId' => $record->getReturnMessageId(),
            'osBonus' => $record->getOsBonus(),
            'orBonus' => $record->getOrBonus(),
            'olBonus' => $record->getOlBonus(),
            'sectionVisibility' => $record->getSectionVisibility(),
            'approvedCharacterVersion' => $record->getApprovedCharacterVersion(),
        ];
    }

    /**
     * Допуск посчитан вместе с reviewState.
     *
     * @param string|null $reviewState Код.
     * @param bool|null $needsModeration Модерация.
     * @param bool|null $canStartSession Допуск.
     * @param bool|null $isActiveSessionParticipant Участник сессии.
     *
     * @return void
     *
     * @throws GameInvalidException Если actual не читали.
     */
    private function assertReviewed(
        ?string $reviewState,
        ?bool $needsModeration,
        ?bool $canStartSession,
        ?bool $isActiveSessionParticipant,
    ): void {
        if ($reviewState === null || $needsModeration === null || $canStartSession === null || $isActiveSessionParticipant === null) {
            throw new GameInvalidException('Game character review is missing');
        }
    }
}
