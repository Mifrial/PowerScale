<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная строка участника. Бонусов и прав на ней нет.
 */
final class GameMemberRecord
{
    private const INVALID = 'Game member row is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Id строки.
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $role Роль gm или player.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $userId,
        private readonly string $role,
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
            GameRowFields::string($fields, 'role', self::INVALID),
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
     * @return int Id игры.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }

    /**
     * Учётка.
     *
     * @return int Id пользователя.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Роль.
     *
     * @return string gm или player.
     */
    public function getRole(): string
    {
        return $this->role;
    }




}
