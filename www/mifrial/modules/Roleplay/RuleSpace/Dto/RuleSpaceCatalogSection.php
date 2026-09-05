<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto;

/**
 * Узел дерева каталога.
 */
final class RuleSpaceCatalogSection
{
    /**
     * Создаёт узел.
     *
     * @param string $code Ключ в снимке.
     * @param string $name Подпись.
     * @param string|null $parentCode Родитель или null.
     * @param int $sortOrder Порядок среди братьев.
     * @param string|null $catalogRootFor Область UI или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly ?string $parentCode,
        private readonly int $sortOrder,
        private readonly ?string $catalogRootFor,
    ) {
    }

    /**
     * Ключ узла.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
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
     * Порядок среди братьев.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Корень области UI.
     *
     * @return string|null Метка или null.
     */
    public function getCatalogRootFor(): ?string
    {
        return $this->catalogRootFor;
    }
}
