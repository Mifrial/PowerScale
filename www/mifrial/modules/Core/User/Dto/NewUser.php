<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

/**
 * Поля новой учётки без registered_at.
 */
final class NewUser
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
     * Оборачивает нормализованные свойства профиля.
     *
     * @param array<string, mixed> $values Уже проверенный набор.
     *
     * @return self Новая учётка.
     */
    public static function fromNormalized(array $values): self
    {
        return new self($values);
    }

    /**
     * Возвращает свойства профиля.
     *
     * @return array<string, mixed> Присутствующие свойства.
     */
    public function fields(): array
    {
        return $this->values;
    }
}
