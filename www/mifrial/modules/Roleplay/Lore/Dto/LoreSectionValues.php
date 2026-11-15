<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Проверенный узел дерева разделов для записи.
 */
final class LoreSectionValues
{
    /**
     * Собирает узел.
     *
     * @param string $code Код узла.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param string|null $parentCode Код родителя.
     * @param int $sortOrder Порядок.
     * @param string $icon Иконка mdi.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly ?string $parentCode,
        private readonly int $sortOrder,
        private readonly string $icon,
    ) {
    }

    /**
     * Код узла.
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
     * Код родителя.
     *
     * @return string|null Код или null.
     */
    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }

    /**
     * Порядок.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * mdi-иконка.
     *
     * @return string Иконка или пустая строка.
     */
    public function getIcon(): string
    {
        return $this->icon;
    }
}
