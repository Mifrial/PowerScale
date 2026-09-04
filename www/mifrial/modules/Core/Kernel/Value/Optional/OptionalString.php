<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value\Optional;

/**
 * Строковое поле JSON: ключа нет или ключ есть (в том числе null).
 */
final class OptionalString extends OptionalValue
{
    /**
     * Создаёт обёртку.
     *
     * @param bool $isPresent Ключ был.
     * @param string|null $value Значение, если ключ был.
     *
     * @return void
     */
    private function __construct(
        bool $isPresent,
        private readonly ?string $value,
    ) {
        parent::__construct($isPresent);
    }

    /**
     * Ключа нет.
     *
     * @return static Absent.
     */
    public static function absent(): static
    {
        return new self(false, null);
    }

    /**
     * Ключ есть.
     *
     * @param string|null $value Строка или JSON null.
     *
     * @return self Present.
     */
    public static function present(?string $value): self
    {
        return new self(true, $value);
    }

    /**
     * Собирает present из JSON: строка или null.
     *
     * @param mixed $jsonValue Значение ключа.
     *
     * @return static Present.
     */
    public static function fromJson(mixed $jsonValue): static
    {
        if ($jsonValue === null || is_string($jsonValue)) {
            return self::present($jsonValue);
        }

        self::rejectJson();
    }

    /**
     * Значение ключа.
     *
     * @return string|null Строка или null.
     */
    public function getValue(): ?string
    {
        $this->assertPresent();

        return $this->value;
    }
}
