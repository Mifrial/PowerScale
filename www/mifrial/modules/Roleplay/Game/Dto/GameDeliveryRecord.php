<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка доставки уже применённой команды.
 */
final class GameDeliveryRecord
{
    private const INVALID = 'Game delivery row is invalid';

    /**
     * Создаёт запись.
     *
     * @param int $id Курсор.
     * @param int $gameId Игра.
     * @param string $source Источник economy или strike.
     * @param int $sourceId Id журнала.
     * @param array<int, array<string, mixed>> $keys Ключи листов.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly string $source,
        private readonly int $sourceId,
        private readonly array $keys,
    ) {
    }

    /**
     * Строка из нормализованных полей.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Строка.
     *
     * @throws GameInvalidException Если поле битое.
     */
    public static function fromNormalized(array $fields): self
    {
        $keys = $fields['keys'] ?? null;
        if (!is_array($keys)) {
            throw new GameInvalidException(self::INVALID);
        }

        return new self(
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::string($fields, 'source', self::INVALID),
            GameRowFields::int($fields, 'source_id', self::INVALID),
            $keys,
        );
    }

    /**
     * Курсор.
     *
     * @return int Id строки.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Вид команды.
     *
     * @return string Источник economy или strike.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Id строки журнала.
     *
     * @return int Id.
     */
    public function getSourceId(): int
    {
        return $this->sourceId;
    }

    /**
     * Игра строки.
     *
     * @return int Id игры.
     */
    public function getGameId(): int
    {
        return $this->gameId;
    }

    /**
     * Ключи листов.
     *
     * @return array<int, array<string, mixed>> Список.
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
