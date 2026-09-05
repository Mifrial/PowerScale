<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Параметры ctor — поля сообщения, не порты DI.

namespace Mifrial\Messages\Chat\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Прочитанное сообщение: смысл свойств, не мешок колонок.
 */
final class MessageRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'chat_id',
        'user_id',
        'content',
        'attachments',
        'audience',
        'audience_user_ids',
        'created_at',
        'updated_at',
    ];

    /**
     * Создаёт запись из свойств сообщения.
     *
     * @param int $id Идентификатор.
     * @param int $chatId Чат.
     * @param int $userId Автор.
     * @param string $content Текст.
     * @param array<int, array{type: string, payload: mixed}> $attachments Вложения.
     * @param string $audience `all` или `users`.
     * @param array<int, int> $audienceUserIds Id при `users`.
     * @param DateTime $createdAt Создание.
     * @param DateTime $updatedAt Обновление.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $chatId,
        private readonly int $userId,
        private readonly string $content,
        private readonly array $attachments,
        private readonly string $audience,
        private readonly array $audienceUserIds,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства сообщения.
     *
     * @return self Сообщение.
     *
     * @throws ChatInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireInt($fields['chat_id']),
            self::requireInt($fields['user_id']),
            self::requireString($fields['content']),
            self::parseAttachments($fields['attachments']),
            self::requireString($fields['audience']),
            self::requireIntList($fields['audience_user_ids']),
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
     * Чат.
     *
     * @return int Id чата.
     */
    public function getChatId(): int
    {
        return $this->chatId;
    }

    /**
     * Автор.
     *
     * @return int Id учётки.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Текст.
     *
     * @return string Содержание.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Вложения.
     *
     * @return array<int, array{type: string, payload: mixed}> Список.
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Колонка audience.
     *
     * @return string `all` или `users`.
     */
    public function getAudience(): string
    {
        return $this->audience;
    }

    /**
     * Id зрителей при `users`.
     *
     * @return array<int, int> Список.
     */
    public function getAudienceUserIds(): array
    {
        return $this->audienceUserIds;
    }

    /**
     * Момент создания.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Момент обновления.
     *
     * @return DateTime UTC.
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Все ключи Record на месте.
     *
     * @param array<string, mixed> $fields Карта строки.
     *
     * @return void
     *
     * @throws ChatInvalidException Если ключа нет.
     */
    private static function assertComplete(array $fields): void
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new ChatInvalidException('Message record is incomplete');
            }
        }
    }

    /**
     * Целое, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return int Целое.
     *
     * @throws ChatInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new ChatInvalidException('Message record is incomplete');
        }

        return $value;
    }

    /**
     * Строка, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return string Строка.
     *
     * @throws ChatInvalidException Если не строка.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ChatInvalidException('Message record is incomplete');
        }

        return $value;
    }

    /**
     * List int, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return array<int, int> Id.
     *
     * @throws ChatInvalidException Если не list int.
     */
    private static function requireIntList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ChatInvalidException('Message record is incomplete');
        }

        $userIds = [];
        foreach ($value as $userId) {
            if (!is_int($userId)) {
                throw new ChatInvalidException('Message record is incomplete');
            }

            $userIds[] = $userId;
        }

        return $userIds;
    }

    /**
     * DateTime ядра, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return DateTime UTC.
     *
     * @throws ChatInvalidException Если не DateTime.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new ChatInvalidException('Message record is incomplete');
        }

        return $value;
    }

    /**
     * Вложения из JSON-колонки.
     *
     * @param mixed $attachments Значение строки.
     *
     * @return array<int, array{type: string, payload: mixed}> Список.
     *
     * @throws ChatInvalidException Если форма неверна.
     */
    private static function parseAttachments(mixed $attachments): array
    {
        if (!is_array($attachments)) {
            throw new ChatInvalidException('Message record is incomplete');
        }

        $parsed = [];
        foreach ($attachments as $attachment) {
            $parsed[] = self::parseAttachment($attachment);
        }

        return $parsed;
    }

    /**
     * Одно вложение.
     *
     * @param mixed $attachment Элемент.
     *
     * @return array{type: string, payload: mixed} Вложение.
     *
     * @throws ChatInvalidException Если форма неверна.
     */
    private static function parseAttachment(mixed $attachment): array
    {
        if (!is_array($attachment) || !is_string($attachment['type'] ?? null)) {
            throw new ChatInvalidException('Message record is incomplete');
        }

        return [
            'type' => $attachment['type'],
            'payload' => $attachment['payload'] ?? null,
        ];
    }
}
