<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies -- восемь колонок строки.

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Прочитанная запись летописи.
 */
final class GameChronicleEntryRecord
{
    /**
     * Создаёт запись.
     *
     * @param int $id Id.
     * @param int $gameId Игра.
     * @param string $title Заголовок.
     * @param string $content Текст.
     * @param int $offsetMinutes Смещение в минутах.
     * @param int $createdBy Кто создал.
     * @param DateTime $createdAt Создание.
     * @param DateTime $updatedAt Правка.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $gameId,
        private readonly string $title,
        private readonly string $content,
        private readonly int $offsetMinutes,
        private readonly int $createdBy,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
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
            self::requireString($fields['title'] ?? null),
            self::requireString($fields['content'] ?? null),
            self::requireInt($fields['offset_minutes'] ?? null),
            self::requireInt($fields['created_by'] ?? null),
            self::requireDateTime($fields['created_at'] ?? null),
            self::requireDateTime($fields['updated_at'] ?? null),
        );
    }

    /**
     * Id записи.
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
     * Заголовок.
     *
     * @return string Текст.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Тело.
     *
     * @return string Текст.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Смещение от эпохи.
     *
     * @return int Минуты.
     */
    public function getOffsetMinutes(): int
    {
        return $this->offsetMinutes;
    }

    /**
     * Автор.
     *
     * @return int Id учётки.
     */
    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    /**
     * Момент создания.
     *
     * @return DateTime Unix.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Момент правки.
     *
     * @return DateTime Unix.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Требует int.
     *
     * @param mixed $value Кандидат.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если тип неверен.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new GameInvalidException('Game chronicle entry row is invalid');
        }

        return $value;
    }

    /**
     * Требует строку.
     *
     * @param mixed $value Кандидат.
     *
     * @return string Значение.
     *
     * @throws GameInvalidException Если тип неверен.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new GameInvalidException('Game chronicle entry row is invalid');
        }

        return $value;
    }

    /**
     * Требует момент.
     *
     * @param mixed $value Кандидат.
     *
     * @return DateTime Момент.
     *
     * @throws GameInvalidException Если тип неверен.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new GameInvalidException('Game chronicle entry row is invalid');
        }

        return $value;
    }
}
