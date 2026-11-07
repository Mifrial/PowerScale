<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанное приглашение.
 */
final class GameInvitationRecord
{
    private const INVALID = 'Game invitation row is invalid';

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
        return new self(
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::int($fields, 'inviter_id', self::INVALID),
            GameRowFields::int($fields, 'invitee_id', self::INVALID),
            GameRowFields::string($fields, 'status', self::INVALID),
        );
    }

    /**
     * Id строки.
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
