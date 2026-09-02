<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

/**
 * Поля новой группы без created_at.
 */
final class NewGroup
{
    /**
     * Создаёт DTO из уже проверенных полей.
     *
     * @param array<string, mixed> $values Нормализованные свойства.
     *
     * @return void
     */
    private function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * Оборачивает нормализованные свойства группы.
     *
     * @param array<string, mixed> $values Уже проверенный набор.
     *
     * @return self Новая группа.
     */
    public static function fromNormalized(array $values): self
    {
        return new self($values);
    }

    /**
     * Возвращает свойства группы.
     *
     * @return array<string, mixed> Присутствующие свойства.
     */
    public function fields(): array
    {
        return $this->values;
    }
}
