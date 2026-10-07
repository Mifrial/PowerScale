<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строка доставки уже применённой команды.
 */
final class GameDeliveryRecord
{
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
        $id = $fields['id'] ?? null;
        $gameId = $fields['game_id'] ?? null;
        $sourceId = $fields['source_id'] ?? null;
        $source = $fields['source'] ?? null;
        if (!is_array($keys) || !is_int($id) || !is_int($gameId) || !is_int($sourceId) || !is_string($source)) {
            throw new GameInvalidException('Game delivery row is invalid');
        }

        return new self($id, $gameId, $source, $sourceId, $keys);
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
     * Ключи листов.
     *
     * @return array<int, array<string, mixed>> Список.
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
