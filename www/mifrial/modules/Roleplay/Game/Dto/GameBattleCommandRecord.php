<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка команды боя.
 */
final class GameBattleCommandRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly int $sessionId,
        private readonly string $idempotencyKey,
        private readonly array $body,
        private readonly array $result,
    ) {
    }

    /**
     * Запись из строки таблицы.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Строка.
     *
     * @throws GameInvalidException Если поле битое.
     */
    public static function fromNormalized(array $fields): self
    {
        $body = $fields['body'] ?? null;
        $result = $fields['result'] ?? null;
        $id = $fields['id'] ?? null;
        $gameId = $fields['game_id'] ?? null;
        $sessionId = $fields['session_id'] ?? null;
        $key = $fields['idempotency_key'] ?? null;
        if (!is_array($body) || !is_array($result) || !is_int($id) || !is_int($gameId) || !is_int($sessionId)) {
            throw new GameInvalidException('Game battle command row is invalid');
        }

        if (!is_string($key)) {
            throw new GameInvalidException('Game battle command row is invalid');
        }

        return new self($id, $gameId, $sessionId, $key, $body, $result);
    }

    /**
     * Разобранное тело.
     *
     * @return array<string, mixed> Тело.
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Сохранённый итог.
     *
     * @return array<string, mixed> Итог.
     */
    public function getResult(): array
    {
        return $this->result;
    }
}
