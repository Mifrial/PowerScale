<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Параметры ctor и геттеры — поля insert, не порты DI.

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Поля нового персонажа до insert.
 */
final class NewCharacter
{
    /**
     * Создаёт DTO из уже проверенных типов.
     *
     * @param int $ownerUserId Владелец.
     * @param int $spaceId Часы мира.
     * @param int $rulesRevision Номер ревизии.
     * @param string $name Имя (ещё не trim).
     * @param array $choices Build JSON.
     * @param array $sheet Кэш JSON.
     * @param array $visibilityFields Секции для всех.
     * @param string $ownerNotes Заметки.
     * @param bool $active Включён.
     *
     * @return void
     */
    private function __construct(
        private readonly int $ownerUserId,
        private readonly int $spaceId,
        private readonly int $rulesRevision,
        private readonly string $name,
        private readonly array $choices,
        private readonly array $sheet,
        private readonly array $visibilityFields,
        private readonly string $ownerNotes,
        private readonly bool $active,
    ) {
    }

    /**
     * Оборачивает набор свойств.
     *
     * @param array<string, mixed> $values Ключи домена.
     *
     * @return self Новая строка.
     *
     * @throws CharacterInvalidException Если типы неверны.
     */
    public static function fromNormalized(array $values): self
    {
        return new self(
            self::requireInt($values['ownerUserId'] ?? null),
            self::requireInt($values['spaceId'] ?? null),
            self::requireInt($values['rulesRevision'] ?? null),
            self::requireString($values['name'] ?? null),
            self::requireArray($values['choices'] ?? null),
            self::requireArray($values['sheet'] ?? null),
            self::requireArray($values['visibilityFields'] ?? []),
            self::requireString($values['ownerNotes'] ?? ''),
            self::requireBool($values['active'] ?? true),
        );
    }

    /**
     * Владелец.
     *
     * @return int Id учётки.
     */
    public function getOwnerUserId(): int
    {
        return $this->ownerUserId;
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
     * Имя до trim фасада.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
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
     * @return array Список кодов.
     */
    public function getVisibilityFields(): array
    {
        return $this->visibilityFields;
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
     * Включён ли лист.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Присутствующие ключи New (DEC-079).
     *
     * @return array<string, mixed> Свойства домена.
     */
    public function fields(): array
    {
        return [
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $this->spaceId,
            'rulesRevision' => $this->rulesRevision,
            'name' => $this->name,
            'choices' => $this->choices,
            'sheet' => $this->sheet,
            'visibilityFields' => $this->visibilityFields,
            'ownerNotes' => $this->ownerNotes,
            'active' => $this->active,
        ];
    }

    /**
     * Целое поле New.
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
            throw new CharacterInvalidException('Character values are invalid');
        }

        return $value;
    }

    /**
     * Строковое поле New.
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
            throw new CharacterInvalidException('Character values are invalid');
        }

        return $value;
    }

    /**
     * Массив JSON New.
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
            throw new CharacterInvalidException('Character values are invalid');
        }

        return $value;
    }

    /**
     * Булево поле New.
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
            throw new CharacterInvalidException('Character values are invalid');
        }

        return $value;
    }
}
