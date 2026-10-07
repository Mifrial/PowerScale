<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка проведённой операции.
 */
final class GameEconomyOperationRecord
{
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
        if (!is_array($body) || !is_array($result) || !is_string($fields['idempotency_key'] ?? null)) {
            throw new GameInvalidException('Economy operation row is invalid');
        }

        $id = $fields['id'] ?? null;
        $gameId = $fields['game_id'] ?? null;
        if (!is_int($id) || !is_int($gameId)) {
            throw new GameInvalidException('Economy operation row is invalid');
        }

        return new self($id, $gameId, $fields['idempotency_key'], $body, $result);
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
}
