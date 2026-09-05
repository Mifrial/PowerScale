<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля sidecar — смысл Record, не порты.

namespace Mifrial\Roleplay\RuleSpace\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Прочитанный мир правил.
 */
final class RuleSpaceRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = ['space_id', 'code', 'name', 'owner_id', 'description', 'active', 'created_at'];

    /**
     * Создаёт запись.
     *
     * @param int $spaceId Id часов / мира.
     * @param string $code Ключ URL.
     * @param string $name Подпись.
     * @param int $ownerId Владелец.
     * @param string $description Текст.
     * @param bool $active Включён.
     * @param DateTime $createdAt Создание.
     *
     * @return void
     */
    private function __construct(
        private readonly int $spaceId,
        private readonly string $code,
        private readonly string $name,
        private readonly int $ownerId,
        private readonly string $description,
        private readonly bool $active,
        private readonly DateTime $createdAt,
    ) {
    }

    /**
     * Собирает Record из строки sidecar.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Мир.
     *
     * @throws RuleSpaceInvalidException Если строка неполная.
     */
    public static function fromNormalized(array $fields): self
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new RuleSpaceInvalidException('Rule space record is incomplete');
            }
        }

        return new self(
            self::requireInt($fields['space_id']),
            self::requireString($fields['code']),
            self::requireString($fields['name']),
            self::requireInt($fields['owner_id']),
            self::requireString($fields['description']),
            self::requireBool($fields['active']),
            self::requireDateTime($fields['created_at']),
        );
    }

    /**
     * Id мира (= space_id часов).
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->spaceId;
    }

    /**
     * Ключ URL.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Владелец мира.
     *
     * @return int User id.
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Включён в продукте.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Создание мира.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Целое, иначе неполный Record.
     *
     * @param mixed $value Значение.
     *
     * @return int Целое.
     *
     * @throws RuleSpaceInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new RuleSpaceInvalidException('Rule space record is incomplete');
        }

        return $value;
    }

    /**
     * Строка, иначе неполный Record.
     *
     * @param mixed $value Значение.
     *
     * @return string Строка.
     *
     * @throws RuleSpaceInvalidException Если не строка.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new RuleSpaceInvalidException('Rule space record is incomplete');
        }

        return $value;
    }

    /**
     * Bool, иначе неполный Record.
     *
     * @param mixed $value Значение.
     *
     * @return bool Флаг.
     *
     * @throws RuleSpaceInvalidException Если не bool.
     */
    private static function requireBool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new RuleSpaceInvalidException('Rule space record is incomplete');
        }

        return $value;
    }

    /**
     * DateTime ядра, иначе неполный Record.
     *
     * @param mixed $value Значение.
     *
     * @return DateTime UTC.
     *
     * @throws RuleSpaceInvalidException Если не DateTime.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new RuleSpaceInvalidException('Rule space record is incomplete');
        }

        return $value;
    }
}
