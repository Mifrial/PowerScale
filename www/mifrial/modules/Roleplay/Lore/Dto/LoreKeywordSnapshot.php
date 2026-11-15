<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Снимок признака в файле `powerscale.lore`.
 */
final class LoreKeywordSnapshot
{
    /**
     * Собирает снимок.
     *
     * @param string $code Код.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param bool $active Включён.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly bool $active,
    ) {
    }

    /**
     * Код.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Имя.
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
     * Включён.
     *
     * @return bool Флаг.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
