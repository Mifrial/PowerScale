<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Поля новой строки участника до insert.
 */
final class NewGameMember
{
    /**
     * Создаёт DTO.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $role Роль.
     *
     * @return void
     */
    private function __construct(
        private readonly int $gameId,
        private readonly int $userId,
        private readonly string $role,
    ) {
    }

    /**
     * Оборачивает набор свойств.
     *
     * @param array<string, mixed> $values Ключи домена.
     *
     * @return self Новая строка.
     *
     * @throws GameInvalidException Если типы неверны.
     */
    public static function fromNormalized(array $values): self
    {
        return new self(
            self::requireInt($values['gameId'] ?? null),
            self::requireInt($values['userId'] ?? null),
            self::requireString($values['role'] ?? null),
        );
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
     * Роль.
     *
     * @return string Код.
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Целое.
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

        throw new GameInvalidException('Game member field is invalid');
    }

    /**
     * Строка.
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

        throw new GameInvalidException('Game member field is invalid');
    }
}
