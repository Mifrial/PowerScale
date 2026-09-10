<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Геттеры и ctor — поля строки, не порты DI.

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Прочитанная actual-строка персонажа.
 */
final class CharacterRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = [
        'id',
        'owner_id',
        'space_id',
        'rules_revision',
        'name',
        'active',
        'actual_version',
        'choices',
        'sheet',
        'visibility_fields',
        'is_public',
        'owner_notes',
        'created_at',
        'updated_at',
    ];

    /**
     * Создаёт запись из свойств строки.
     *
     * @param int $id Идентификатор.
     * @param int $ownerId Владелец.
     * @param int $spaceId Часы.
     * @param int $rulesRevision Номер ревизии.
     * @param string $name Имя.
     * @param bool $active Включён.
     * @param int $actualVersion Lock.
     * @param array $choices Build.
     * @param array $sheet Кэш.
     * @param array $visibilityFields Секции всем.
     * @param bool $isPublic Зеркало непустых секций.
     * @param string $ownerNotes Заметки.
     * @param DateTime $createdAt Создание.
     * @param DateTime $updatedAt Обновление.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $ownerId,
        private readonly int $spaceId,
        private readonly int $rulesRevision,
        private readonly string $name,
        private readonly bool $active,
        private readonly int $actualVersion,
        private readonly array $choices,
        private readonly array $sheet,
        private readonly array $visibilityFields,
        private readonly bool $isPublic,
        private readonly string $ownerNotes,
        private readonly DateTime $createdAt,
        private readonly DateTime $updatedAt,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return self Персонаж.
     *
     * @throws CharacterInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireInt($fields['owner_id']),
            self::requireInt($fields['space_id']),
            self::requireInt($fields['rules_revision']),
            self::requireString($fields['name']),
            self::requireBool($fields['active']),
            self::requireInt($fields['actual_version']),
            self::requireArray($fields['choices']),
            self::requireArray($fields['sheet']),
            self::requireArray($fields['visibility_fields']),
            self::requireBool($fields['is_public']),
            self::requireString($fields['owner_notes']),
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
     * Владелец.
     *
     * @return int Id учётки.
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * Часы мира.
     *
     * @return int space_id.
     */
    public function getSpaceId(): int
    {
        return $this->spaceId;
    }

    /**
     * Номер ревизии правил.
     *
     * @return int Номер.
     */
    public function getRulesRevision(): int
    {
        return $this->rulesRevision;
    }

    /**
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Лист включён.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Счётчик записи для optimistic lock.
     *
     * @return int actual_version.
     */
    public function getActualVersion(): int
    {
        return $this->actualVersion;
    }

    /**
     * Выборы.
     *
     * @return array Build.
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Кэш листа.
     *
     * @return array Sheet.
     */
    public function getSheet(): array
    {
        return $this->sheet;
    }

    /**
     * Секции для всех.
     *
     * @return array Коды.
     */
    public function getVisibilityFields(): array
    {
        return $this->visibilityFields;
    }

    /**
     * Есть непустой грант «всем».
     *
     * @return bool is_public.
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * Личные заметки.
     *
     * @return string Текст.
     */
    public function getOwnerNotes(): string
    {
        return $this->ownerNotes;
    }

    /**
     * Создание.
     *
     * @return DateTime UTC.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Обновление.
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
     * @throws CharacterInvalidException Если ключа нет.
     */
    private static function assertComplete(array $fields): void
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new CharacterInvalidException('Character record is incomplete');
            }
        }
    }

    /**
     * Целое.
     *
     * @param mixed $value Значение.
     *
     * @return int Целое.
     *
     * @throws CharacterInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new CharacterInvalidException('Character record is incomplete');
        }

        return $value;
    }

    /**
     * Строка.
     *
     * @param mixed $value Значение.
     *
     * @return string Строка.
     *
     * @throws CharacterInvalidException Если не строка.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new CharacterInvalidException('Character record is incomplete');
        }

        return $value;
    }

    /**
     * Булево свойство строки.
     *
     * @param mixed $value Значение.
     *
     * @return bool Флаг.
     *
     * @throws CharacterInvalidException Если не bool.
     */
    private static function requireBool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new CharacterInvalidException('Character record is incomplete');
        }

        return $value;
    }

    /**
     * JSON-массив.
     *
     * @param mixed $value Значение.
     *
     * @return array Массив.
     *
     * @throws CharacterInvalidException Если не array.
     */
    private static function requireArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new CharacterInvalidException('Character record is incomplete');
        }

        return $value;
    }

    /**
     * DateTime ядра.
     *
     * @param mixed $value Значение.
     *
     * @return DateTime UTC.
     *
     * @throws CharacterInvalidException Если не DateTime.
     */
    private static function requireDateTime(mixed $value): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new CharacterInvalidException('Character record is incomplete');
        }

        return $value;
    }
}
