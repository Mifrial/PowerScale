<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Dto;

/**
 * Поля новой строки файла.
 */
final class NewFile
{
    /**
     * Создаёт DTO из уже проверенных полей.
     *
     * @param array<string, mixed> $values Нормализованные поля.
     *
     * @return void
     */
    private function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * Оборачивает нормализованные поля.
     *
     * @param array<string, mixed> $values Уже проверенный набор.
     *
     * @return self Новая строка.
     */
    public static function fromNormalized(array $values): self
    {
        return new self($values);
    }

    /**
     * Возвращает поля строки.
     *
     * @return array<string, mixed> Поля.
     */
    public function fields(): array
    {
        return $this->values;
    }
}
