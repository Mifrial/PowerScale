<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная строка участника. Бонусов и прав на ней нет.
 */
final class GameMemberRecord
{
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
            self::requireInt($fields['id'] ?? null),
            self::requireInt($fields['game_id'] ?? null),
            self::requireInt($fields['user_id'] ?? null),
            self::requireString($fields['role'] ?? null),
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

    /**
     * Целое из колонки.
     *
     * @param mixed $value Значение.
     *
     * @return int Число.
     *
     * @throws GameInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        throw new GameInvalidException('Game member row is invalid');
    }

    /**
     * Строка из колонки.
     *
     * @param mixed $value Значение.
     *
     * @return string Текст.
     *
     * @throws GameInvalidException Если не string.
     */
    private static function requireString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new GameInvalidException('Game member row is invalid');
    }
}
