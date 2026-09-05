<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto;

/**
 * Карточка правила в секции снимка.
 */
final class RuleSpaceCatalogPlacement
{
    /**
     * Создаёт размещение.
     *
     * @param string $ruleCode Identity правила.
     * @param string $sectionCode Код узла снимка.
     * @param int $sortOrder Порядок в папке.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly string $sectionCode,
        private readonly int $sortOrder,
    ) {
    }

    /**
     * Код правила.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Код секции.
     *
     * @return string Код.
     */
    public function getSectionCode(): string
    {
        return $this->sectionCode;
    }

    /**
     * Порядок в папке.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
