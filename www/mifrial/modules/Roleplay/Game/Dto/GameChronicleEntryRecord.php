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
    private const INVALID = 'Game chronicle entry row is invalid';

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
            GameRowFields::int($fields, 'id', self::INVALID),
            GameRowFields::int($fields, 'game_id', self::INVALID),
            GameRowFields::string($fields, 'title', self::INVALID),
            GameRowFields::string($fields, 'content', self::INVALID),
            GameRowFields::int($fields, 'offset_minutes', self::INVALID),
            GameRowFields::int($fields, 'created_by', self::INVALID),
            GameRowFields::dateTime($fields, 'created_at', self::INVALID),
            GameRowFields::dateTime($fields, 'updated_at', self::INVALID),
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






}
