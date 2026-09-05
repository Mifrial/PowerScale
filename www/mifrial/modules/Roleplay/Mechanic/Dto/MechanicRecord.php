<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;

/**
 * Прочитанная поставка механики.
 */
final class MechanicRecord
{
    /**
     * @var array<int, string>
     */
    private const FIELD_NAMES = ['id', 'code', 'name', 'description', 'handler_version'];

    /**
     * Создаёт запись из свойств строки.
     *
     * @param int $id Идентификатор.
     * @param string $code Код семейства.
     * @param string $name Подпись.
     * @param string $description Текст; пустая строка допустима.
     * @param string $handlerVersion Поставка контракта.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly string $handlerVersion,
    ) {
    }

    /**
     * Собирает Record из полного набора свойств строки.
     *
     * @param array<string, mixed> $fields Свойства механики.
     *
     * @return self Механика.
     *
     * @throws MechanicInvalidException Если нет свойства или тип не тот.
     */
    public static function fromNormalized(array $fields): self
    {
        self::assertComplete($fields);

        return new self(
            self::requireInt($fields['id']),
            self::requireString($fields['code']),
            self::requireString($fields['name']),
            self::requireString($fields['description']),
            self::requireString($fields['handler_version']),
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
     * Код семейства хендлера.
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
     * Поставка контракта.
     *
     * @return string Версия хендлера.
     */
    public function getHandlerVersion(): string
    {
        return $this->handlerVersion;
    }

    /**
     * Все ключи Record на месте.
     *
     * @param array<string, mixed> $fields Карта строки.
     *
     * @return void
     *
     * @throws MechanicInvalidException Если ключа нет.
     */
    private static function assertComplete(array $fields): void
    {
        foreach (self::FIELD_NAMES as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                throw new MechanicInvalidException('Mechanic record is incomplete');
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
     * @throws MechanicInvalidException Если не int.
     */
    private static function requireInt(mixed $value): int
    {
        if (!is_int($value)) {
            throw new MechanicInvalidException('Mechanic record is incomplete');
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
     * @throws MechanicInvalidException Если не строка.
     */
    private static function requireString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new MechanicInvalidException('Mechanic record is incomplete');
        }

        return $value;
    }
}
