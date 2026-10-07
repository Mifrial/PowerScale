<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная заявка.
 */
final class GameJoinRequestRecord
{
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
        $id = $fields['id'] ?? null;
        $gameId = $fields['game_id'] ?? null;
        $userId = $fields['user_id'] ?? null;
        $status = $fields['status'] ?? null;
        if (!is_int($id) || !is_int($gameId) || !is_int($userId) || !is_string($status)) {
            throw new GameInvalidException('Game join request row is invalid');
        }

        return new self($id, $gameId, $userId, $status);
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
