<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка проведённой операции.
 */
final class GameEconomyOperationRecord
{
    private const INVALID = 'Economy operation row is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Разобранное тело.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    public function __construct(
        private readonly int $id,
        private readonly int $gameId,
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
        if (!is_array($body) || !is_array($result)) {
            throw new GameInvalidException(self::INVALID);
        }

        return new self(
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::string($fields, 'idempotency_key', self::INVALID),
            $body,
            $result,
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
     * Ключ повтора.
     *
     * @return string Ключ.
     */
    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * Тело, с которым сравнивается повтор.
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
     * @return array<string, mixed> Ответ.
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Игра операции.
     *
     * @return int Id игры.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }
}
