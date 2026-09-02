<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

/**
 * Частичное обновление группы: только переданные свойства.
 */
final class GroupPatch
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
     * @return self Patch.
     */
    public static function fromNormalized(array $values): self
    {
        return new self($values);
    }

    /**
     * Возвращает присутствующие свойства.
     *
     * @return array<string, mixed> Свойства.
     */
    public function fields(): array
    {
        return $this->values;
    }
}
