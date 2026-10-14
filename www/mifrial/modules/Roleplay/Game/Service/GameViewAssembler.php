<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Плоский JSON игры. sessionRunning истинен, когда строка сессии есть.
 */
final class GameViewAssembler
{
    /**
     * Карточка списка.
     *
     * @param GameRecord $record Строка.
     * @param bool $sessionRunning Текущая сессия есть.
     *
     * @return array<string, mixed> JSON.
     */
    public function listItem(GameRecord $record, bool $sessionRunning): array
    {
        return [
            'id' => $record->getId(),
            'name' => $record->getName(),
            'shortDescription' => $record->getShortDescription(),
            'status' => $record->getStatus(),
            'sessionRunning' => $sessionRunning,
            'visibility' => $record->getVisibility(),
            'joinPolicy' => $record->getJoinPolicy(),
            'ownerId' => $record->getOwnerId(),
            'spaceId' => $record->getSpaceId(),
            'spaceCode' => $record->getSpaceCode(),
            'rulesRevision' => $record->getRulesRevision(),
            'gameChatId' => $record->getGameChatId(),
            'discussionChatId' => $record->getDiscussionChatId(),
        ];
    }

    /**
     * Карточка с описанием и потолками.
     *
     * @param GameRecord $record Строка.
     * @param bool $sessionRunning Текущая сессия есть.
     * @param GameBattleRecord|null $battle Открытый бой или null.
     *
     * @return array<string, mixed> JSON.
     */
    public function detail(GameRecord $record, bool $sessionRunning, ?GameBattleRecord $battle = null): array
    {
        return $this->listItem($record, $sessionRunning) + [
            'description' => $record->getDescription(),
            'osPointsLimit' => $record->getOsPointsLimit(),
            'olPointsLimit' => $record->getOlPointsLimit(),
            'orPointsLimit' => $record->getOrPointsLimit(),
            'moneyLimit' => $record->getMoneyLimit(),
            'createdAt' => $record->getCreatedAt()->toUnix(),
            'updatedAt' => $record->getUpdatedAt()->toUnix(),
            'battle' => $this->battleOf($battle),
        ];
    }

    /**
     * Снимок открытого боя или null.
     *
     * @param GameBattleRecord|null $battle Строка.
     *
     * @return array<string, mixed>|null JSON.
     */
    private function battleOf(?GameBattleRecord $battle): ?array
    {
        if ($battle === null) {
            return null;
        }

        return [
            'battleId' => $battle->getId(),
            'version' => $battle->getStateVersion(),
            'order' => $battle->getTurnOrder(),
            'activeIndex' => $battle->getActiveIndex(),
            'round' => $battle->getRound(),
        ];
    }

    /**
     * Участник без имени и без списка прав.
     *
     * @param GameMemberRecord $record Строка.
     *
     * @return array<string, mixed> JSON.
     */
    public function member(GameMemberRecord $record): array
    {
        return [
            'userId' => $record->getUserId(),
            'role' => $record->getRole(),
        ];
    }
}
