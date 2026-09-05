<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Прочитанный чат: тип, имя и моменты, без pair_key.
 */
final class ChatRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = ['id', 'type', 'name', 'created_at', 'updated_at'];

    /**
     * Создаёт запись из свойств чата.
     *
     * @param int $id Идентификатор.
     * @param string $type private или group.
     * @param string $name Имя; у private пустая строка.
     * @param DateTime $createdAt Создание.
     * @param DateTime $updatedAt Обновление.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $type,
        private readonly string $name,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства чата.
     *
     * @return self Чат.
     *
     * @throws ChatInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireString($fields['type']),
            self::requireString($fields['name']),
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
     * Тип чата.
     *
     * @return string private или group.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Имя чата.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
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
                throw new ChatInvalidException('Chat record is incomplete');
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
            throw new ChatInvalidException('Chat record is incomplete');
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
            throw new ChatInvalidException('Chat record is incomplete');
        }

        return $value;
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
            throw new ChatInvalidException('Chat record is incomplete');
        }

        return $value;
    }
}
