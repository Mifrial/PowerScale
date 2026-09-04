<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Value\Optional;

/**
 * Массив в JSON: ключа нет или ключ есть (не null).
 */
final class OptionalArray extends OptionalValue
{
    /**
     * Создаёт обёртку.
     *
     * @param bool $isPresent Ключ был.
     * @param array<int|string, mixed> $value Массив, если ключ был.
     *
     * @return void
     */
    private function __construct(
        bool $isPresent,
        private readonly array $value,
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
        return new self(false, []);
    }

    /**
     * Ключ есть.
     *
     * @param array<int|string, mixed> $value Массив.
     *
     * @return self Present.
     */
    public static function present(array $value): self
    {
        return new self(true, $value);
    }

    /**
     * Собирает present из JSON: только массив.
     *
     * @param mixed $jsonValue Значение ключа.
     *
     * @return static Present.
     */
    public static function fromJson(mixed $jsonValue): static
    {
        if (is_array($jsonValue)) {
            return self::present($jsonValue);
        }

        self::rejectJson();
    }

    /**
     * Значение ключа.
     *
     * @return array<int|string, mixed> Массив.
     */
    public function getValue(): array
    {
        $this->assertPresent();

        return $this->value;
    }
}
