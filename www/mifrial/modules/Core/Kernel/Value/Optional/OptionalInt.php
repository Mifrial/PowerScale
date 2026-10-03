<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value\Optional;

/**
 * Целое поле JSON: ключа нет или ключ есть (int или null).
 */
final class OptionalInt extends OptionalValue
{
    /**
     * Создаёт обёртку.
     *
     * @param bool $isPresent Ключ был.
     * @param int|null $value Значение, если ключ был.
     *
     * @return void
     */
    private function __construct(
        bool $isPresent,
        private readonly ?int $value,
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
     * @param int|null $value Целое или JSON null.
     *
     * @return self Present.
     */
    public static function present(?int $value): self
    {
        return new self(true, $value);
    }

    /**
     * Собирает present из JSON: int или null.
     *
     * @param mixed $jsonValue Значение ключа.
     *
     * @return static Present.
     */
    public static function fromJson(mixed $jsonValue): static
    {
        if ($jsonValue === null || is_int($jsonValue)) {
            return self::present($jsonValue);
        }

        self::rejectJson();
    }

    /**
     * Значение ключа.
     *
     * @return int|null Целое или null.
     */
    public function getValue(): ?int
    {
        $this->assertPresent();

        return $this->value;
    }
}
