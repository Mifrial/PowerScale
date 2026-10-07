<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанное приглашение.
 */
final class GameInvitationRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param int $inviterId Кто пригласил.
     * @param int $inviteeId Кого пригласили.
     * @param string $status Статус.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $inviterId,
        private readonly int $inviteeId,
        private readonly string $status,
    ) {
    }

    /**
     * Собирает запись из строки SmartTable.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Запись.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public static function fromNormalized(array $fields): self
    {
        $id = $fields['id'] ?? null;
        $gameId = $fields['game_id'] ?? null;
        $inviterId = $fields['inviter_id'] ?? null;
        $inviteeId = $fields['invitee_id'] ?? null;
        $status = $fields['status'] ?? null;
        if (!is_int($id) || !is_int($gameId) || !is_int($inviterId) || !is_int($inviteeId) || !is_string($status)) {
            throw new GameInvalidException('Game invitation row is invalid');
        }

        return new self($id, $gameId, $inviterId, $inviteeId, $status);
    }

    /**
     * Id.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Игра.
     *
     * @return int Id.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }

    /**
     * Кто пригласил.
     *
     * @return int Id.
     */
    public function getInviterId(): int
    {
        return $this->inviterId;
    }

    /**
     * Кого пригласили.
     *
     * @return int Id.
     */
    public function getInviteeId(): int
    {
        return $this->inviteeId;
    }

    /**
     * Статус.
     *
     * @return string Код.
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
