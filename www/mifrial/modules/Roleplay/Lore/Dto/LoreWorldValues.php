<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Проверенные поля мира для записи.
 */
final class LoreWorldValues
{
    /**
     * Собирает поля.
     *
     * @param string $code Код URL.
     * @param string $name Название.
     * @param string $description Описание.
     * @param string $visibility Public или private.
     * @param string $spaceCode Мягкая ссылка на пространство правил.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly string $visibility,
        private readonly string $spaceCode,
    ) {
    }

    /**
     * Код URL.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Название.
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
     * Видимость.
     *
     * @return string public или private.
     */
    public function getVisibility(): string
    {
        return $this->visibility;
    }

    /**
     * Мягкая ссылка на пространство правил.
     *
     * @return string Код или пустая строка.
     */
    public function getSpaceCode(): string
    {
        return $this->spaceCode;
    }
}
