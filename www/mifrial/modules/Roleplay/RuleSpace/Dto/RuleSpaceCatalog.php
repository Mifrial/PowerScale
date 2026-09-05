<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto;

use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Service\RuleSpaceCatalogGuard;

/**
 * Снимок дерева секций и размещений.
 */
final class RuleSpaceCatalog
{
    /**
     * Создаёт снимок.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     * @param array<int, RuleSpaceCatalogPlacement> $placements Карточки.
     *
     * @return void
     */
    private function __construct(
        private readonly array $sections,
        private readonly array $placements,
    ) {
    }

    /**
     * Пустое дерево.
     *
     * @return self Снимок.
     */
    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * Собирает снимок из частей.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     * @param array<int, RuleSpaceCatalogPlacement> $placements Карточки.
     *
     * @return self Снимок.
     *
     * @throws RuleSpaceInvalidException Если инвариант нарушен.
     */
    public static function fromParts(array $sections, array $placements): self
    {
        if (!array_is_list($sections) || !array_is_list($placements)) {
            throw new RuleSpaceInvalidException('Catalog snapshot is invalid');
        }

        (new RuleSpaceCatalogGuard())->assertSnapshot($sections, $placements);

        return new self($sections, $placements);
    }

    /**
     * Узлы дерева.
     *
     * @return array<int, RuleSpaceCatalogSection> Узлы.
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Размещения.
     *
     * @return array<int, RuleSpaceCatalogPlacement> Карточки.
     */
    public function getPlacements(): array
    {
        return $this->placements;
    }

    /**
     * Канон для шаринга снимка.
     *
     * @return string Отпечаток.
     */
    public function fingerprint(): string
    {
        $sectionRows = [];
        foreach ($this->sections as $section) {
            $sectionRows[$section->getCode()] = [
                $section->getCode(),
                $section->getName(),
                $section->getParentCode(),
                $section->getSortOrder(),
                $section->getCatalogRootFor(),
            ];
        }

        ksort($sectionRows);
        $placementRows = [];
        foreach ($this->placements as $placement) {
            $placementRows[$placement->getRuleCode()] = [
                $placement->getRuleCode(),
                $placement->getSectionCode(),
                $placement->getSortOrder(),
            ];
        }

        ksort($placementRows);

        return serialize([array_values($sectionRows), array_values($placementRows)]);
    }
}
