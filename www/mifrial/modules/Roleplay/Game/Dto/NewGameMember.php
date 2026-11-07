<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Поля новой строки участника до insert.
 */
final class NewGameMember
{
    private const INVALID = 'Game member field is invalid';

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
            GameRowFields::int($values, 'gameId', self::INVALID),
            GameRowFields::int($values, 'userId', self::INVALID),
            GameRowFields::string($values, 'role', self::INVALID),
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




}
