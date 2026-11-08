<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Геттеры — поля строки, не порты DI.

namespace Mifrial\Messages\Notes\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Notes\Exception\NoteInvalidException;

/**
 * Прочитанная заметка: смысл свойств, не мешок колонок.
 */
final class NoteRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'host_type',
        'host_id',
        'author_id',
        'title',
        'body_html',
        'body_text',
        'pinned',
        'sort_order',
        'active',
        'created_at',
        'updated_at',
    ];

    /**
     * Создаёт запись из свойств заметки.
     *
     * @param int $id Идентификатор.
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param int $authorId Автор.
     * @param string $title Заголовок.
     * @param string $bodyHtml HTML.
     * @param string $bodyText Текст для поиска.
     * @param bool $pinned Закреплена.
     * @param int $sortOrder Ручной порядок.
     * @param bool $active Активна.
     * @param DateTime $createdAt Создание.
     * @param DateTime $updatedAt Обновление.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $hostType,
        private readonly int $hostId,
        private readonly int $authorId,
        private readonly string $title,
        private readonly string $bodyHtml,
        private readonly string $bodyText,
        private readonly bool $pinned,
        private readonly int $sortOrder,
        private readonly bool $active,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
    ) {
    }

    /**
     * Собирает запись из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства заметки.
     *
     * @return self Заметка.
     *
     * @throws NoteInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireString($fields['host_type']),
            self::requireInt($fields['host_id']),
            self::requireInt($fields['author_id']),
            self::requireString($fields['title']),
            self::requireString($fields['body_html']),
            self::requireString($fields['body_text']),
            self::requireBool($fields['pinned']),
            self::requireInt($fields['sort_order']),
            self::requireBool($fields['active']),
            self::requireDateTime($fields['created_at']),
            self::requireDateTime($fields['updated_at']),
        );
    }

    /**
     * Идентификатор.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Тип сущности.
     *
     * @return string Тип.
     */
    public function getHostType(): string
    {
        return $this->hostType;
    }

    /**
     * Id сущности.
     *
     * @return int Id.
     */
    public function getHostId(): int
    {
        return $this->hostId;
    }

    /**
     * Автор.
     *
     * @return int Id учётки.
     */
    public function getAuthorId(): int
    {
        return $this->authorId;
    }

    /**
     * Заголовок.
     *
     * @return string Заголовок.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * HTML.
     *
     * @return string HTML.
     */
    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    /**
     * Текст для поиска.
     *
     * @return string Текст.
     */
    public function getBodyText(): string
    {
        return $this->bodyText;
    }

    /**
     * Закрепление.
     *
     * @return bool true, если закреплена.
     */
    public function isPinned(): bool
    {
        return $this->pinned;
    }

    /**
     * Ручной порядок.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Активность.
     *
     * @return bool true, если активна.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Создание.
     *
     * @return DateTime Момент.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Обновление.
     *
     * @return DateTime Момент.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Проверяет полноту набора.
     *
     * @param array<string, mixed> $fields Свойства.
     *
     * @return void
     *
     * @throws NoteInvalidException Если поля нет.
     */
    private static function assertComplete(array $fields): void
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new NoteInvalidException('Note row is incomplete: ' . $fieldName);
            }
        }
    }

    /**
     * Целое поле.
     *
     * @param mixed $value Значение.
     *
     * @return int Целое.
     *
     * @throws NoteInvalidException Если тип не тот.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new NoteInvalidException('Note field is not int');
        }

        return $value;
    }

    /**
     * Строковое поле.
     *
     * @param mixed $value Значение.
     *
     * @return string Строка.
     *
     * @throws NoteInvalidException Если тип не тот.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new NoteInvalidException('Note field is not string');
        }

        return $value;
    }

    /**
     * Булево поле.
     *
     * @param mixed $value Значение.
     *
     * @return bool Флаг.
     *
     * @throws NoteInvalidException Если тип не тот.
     */
    private static function requireBool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new NoteInvalidException('Note field is not bool');
        }

        return $value;
    }

    /**
     * Момент времени.
     *
     * @param mixed $value Значение.
     *
     * @return DateTime Момент.
     *
     * @throws NoteInvalidException Если тип не тот.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new NoteInvalidException('Note field is not datetime');
        }

        return $value;
    }
}
