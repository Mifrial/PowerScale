<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Фильтр карточки по visibility. friends без дружбы закрыт.
 */
final class GameCardAccess
{
    /**
     * Создаёт разбор.
     *
     * @param GameRepository $gameRepository Строки игры.
     * @param GameMemberRepository $memberRepository Участники.
     * @param GameInvitationRepository $invitationRepository Приглашения.
     *
     * @return void
     */
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameMemberRepository $memberRepository,
        private readonly GameInvitationRepository $invitationRepository,
    ) {
    }

    /**
     * Видна ли одна карточка.
     *
     * @param GameRecord $record Строка.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return bool Да, если открыта.
     */
    public function isVisible(GameRecord $record, int $actorUserId, bool $viewAll): bool
    {
        if ($record->getOwnerId() === $actorUserId || $viewAll) {
            return true;
        }

        if ($record->getStatus() === 'draft') {
            return false;
        }

        return $this->matches($record, $actorUserId, $this->memberGameIds($actorUserId), $this->invitedGameIds($actorUserId));
    }

    /**
     * Строки списка без game.view_all.
     *
     * @param int $actorUserId Актор.
     *
     * @return list<GameRecord> Видимые.
     */
    public function retainVisible(int $actorUserId): array
    {
        $memberGameIds = $this->memberGameIds($actorUserId);
        $invitedGameIds = $this->invitedGameIds($actorUserId);
        $kept = [];
        foreach ($this->gameRepository->getListForCard() as $record) {
            if ($record->getOwnerId() === $actorUserId || $this->opened($record, $actorUserId, $memberGameIds, $invitedGameIds)) {
                $kept[] = $record;
            }
        }

        return $kept;
    }

    /**
     * Не-черновик проходит visibility.
     *
     * @param GameRecord $record Строка.
     * @param int $actorUserId Актор.
     * @param array<int, true> $memberGameIds Игры участника.
     * @param array<int, true> $invitedGameIds Игры приглашений.
     *
     * @return bool Да, если открыта.
     */
    private function opened(GameRecord $record, int $actorUserId, array $memberGameIds, array $invitedGameIds): bool
    {
        if ($record->getStatus() === 'draft') {
            return false;
        }

        return $this->matches($record, $actorUserId, $memberGameIds, $invitedGameIds);
    }

    /**
     * Предикат visibility. Черновик уже отсечён.
     *
     * @param GameRecord $record Строка.
     * @param int $actorUserId Актор.
     * @param array<int, true> $memberGameIds Игры участника.
     * @param array<int, true> $invitedGameIds Игры приглашений.
     *
     * @return bool Да, если открыта.
     */
    private function matches(GameRecord $record, int $actorUserId, array $memberGameIds, array $invitedGameIds): bool
    {
        $gameId = $record->getId();
        $isMember = isset($memberGameIds[$gameId]);

        return match ($record->getVisibility()) {
            'all' => true,
            'players', 'friends' => $isMember,
            'invited' => $isMember || isset($invitedGameIds[$gameId]),
            'whitelist' => $isMember || in_array($actorUserId, $record->getWhitelist(), true),
            default => false,
        };
    }

    /**
     * Id игр, где актор участник.
     *
     * @param int $actorUserId Актор.
     *
     * @return array<int, true> Множество.
     */
    private function memberGameIds(int $actorUserId): array
    {
        $ids = [];
        foreach ($this->memberRepository->getListByUser($actorUserId) as $record) {
            $ids[$record->getGameId()] = true;
        }

        return $ids;
    }

    /**
     * Id игр с pending или accepted приглашением.
     *
     * @param int $actorUserId Актор.
     *
     * @return array<int, true> Множество.
     */
    private function invitedGameIds(int $actorUserId): array
    {
        $ids = [];
        foreach ($this->invitationRepository->getListByInvitee($actorUserId) as $record) {
            if ($record->getStatus() === 'pending' || $record->getStatus() === 'accepted') {
                $ids[$record->getGameId()] = true;
            }
        }

        return $ids;
    }
}
