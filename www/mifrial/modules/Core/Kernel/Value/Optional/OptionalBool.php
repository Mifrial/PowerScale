<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value\Optional;

/**
 * Булево поле JSON: ключа нет или ключ есть (не null).
 */
final class OptionalBool extends OptionalValue
{
    /**
     * Создаёт обёртку.
     *
     * @param bool $isPresent Ключ был.
     * @param bool $value Флаг, если ключ был.
     *
     * @return void
     */
    private function __construct(
        bool $isPresent,
        private readonly bool $value,
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
        return new self(false, false);
    }

    /**
     * Ключ есть.
     *
     * @param bool $value Флаг.
     *
     * @return self Present.
     */
    public static function present(bool $value): self
    {
        return new self(true, $value);
    }

    /**
     * Собирает present из JSON: только bool.
     *
     * @param mixed $jsonValue Значение ключа.
     *
     * @return static Present.
     */
    public static function fromJson(mixed $jsonValue): static
    {
        if (is_bool($jsonValue)) {
            return self::present($jsonValue);
        }

        self::rejectJson();
    }

    /**
     * Значение ключа.
     *
     * @return bool Флаг.
     */
    public function getValue(): bool
    {
        $this->assertPresent();

        return $this->value;
    }
}
