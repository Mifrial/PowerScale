<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Прочитанное членство: чат, учётка, last_read, вход.
 */
final class ChatMemberRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'chat_id',
        'user_id',
        'last_read_message_id',
        'joined_at',
    ];

    /**
     * Создаёт запись из свойств членства.
     *
     * @param int $id Идентификатор строки.
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     * @param int|null $lastReadMessageId Прочитанное или null.
     * @param DateTime $joinedAt Вход.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $chatId,
        private readonly int $userId,
        private readonly ?int $lastReadMessageId,
        private readonly DateTime $joinedAt,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства членства.
     *
     * @return self Членство.
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
            self::requireNullableInt($fields['last_read_message_id']),
            self::requireDateTime($fields['joined_at']),
        );
    }

    /**
     * Идентификатор строки.
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
     * Учётка.
     *
     * @return int Id учётки.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Последнее прочитанное сообщение.
     *
     * @return int|null Id или null.
     */
    public function getLastReadMessageId(): ?int
    {
        return $this->lastReadMessageId;
    }

    /**
     * Момент входа.
     *
     * @return DateTime UTC.
     */
    public function getJoinedAt(): DateTime
    {
        return $this->joinedAt;
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
                throw new ChatInvalidException('Member record is incomplete');
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
            throw new ChatInvalidException('Member record is incomplete');
        }

        return $value;
    }

    /**
     * Целое или null, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return int|null Целое или null.
     *
     * @throws ChatInvalidException Если не int и не null.
     */
    private static function requireNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::requireInt($value);
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
            throw new ChatInvalidException('Member record is incomplete');
        }

        return $value;
    }
}
