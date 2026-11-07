<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная заявка.
 */
final class GameJoinRequestRecord
{
    private const INVALID = 'Game join request row is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $status Статус.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $userId,
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
            GameRowFields::int($fields, 'user_id', self::INVALID),
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
     * Учётка.
     *
     * @return int Id.
     */
    public function getUserId(): int
    {
        return $this->userId;
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
