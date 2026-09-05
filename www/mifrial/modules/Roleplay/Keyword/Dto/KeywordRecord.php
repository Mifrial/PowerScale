<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Dto;

use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;

/**
 * Прочитанный признак справочника.
 */
final class KeywordRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = ['id', 'code', 'name', 'description', 'active'];

    /**
     * Создаёт запись из свойств строки.
     *
     * @param int $id Идентификатор.
     * @param string $code Семантический ключ.
     * @param string $name Подпись.
     * @param string $description Текст; пустая строка допустима.
     * @param bool $active Включён в справочнике.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly bool $active,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства признака.
     *
     * @return self Признак.
     *
     * @throws KeywordInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireString($fields['code']),
            self::requireString($fields['name']),
            self::requireString($fields['description']),
            self::requireBool($fields['active']),
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
     * Семантический ключ.
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
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Включён в справочнике.
     *
     * @return bool true, если активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Все ключи Record на месте.
     *
     * @param array<string, mixed> $fields Карта строки.
     *
     * @return void
     *
     * @throws KeywordInvalidException Если ключа нет.
     */
    private static function assertComplete(array $fields): void
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new KeywordInvalidException('Keyword record is incomplete');
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
     * @throws KeywordInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new KeywordInvalidException('Keyword record is incomplete');
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
     * @throws KeywordInvalidException Если не строка.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new KeywordInvalidException('Keyword record is incomplete');
        }

        return $value;
    }

    /**
     * Bool, иначе неполный Record.
     *
     * @param mixed $value Значение свойства.
     *
     * @return bool Флаг.
     *
     * @throws KeywordInvalidException Если не bool.
     */
    private static function requireBool(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new KeywordInvalidException('Keyword record is incomplete');
        }

        return $value;
    }
}
