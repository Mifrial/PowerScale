<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Инварианты дерева и размещений снимка.
 */
final class RuleSpaceCatalogGuard
{
    /**
     * Проверяет узлы и размещения.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     * @param array<int, RuleSpaceCatalogPlacement> $placements Карточки.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если дерево или размещения недопустимы.
     */
    public function assertSnapshot(array $sections, array $placements): void
    {
        if (count($sections) > 500 || count($placements) > 10000) {
            throw new RuleSpaceInvalidException('Catalog snapshot is too large');
        }

        $sectionCodes = $this->sectionCodeSet($sections);
        $this->assertParents($sections, $sectionCodes);
        $this->assertPlacements($placements, $sectionCodes);
    }

    /**
     * Уникальные коды узлов.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     *
     * @return array<string, true> Коды.
     *
     * @throws RuleSpaceInvalidException Если дубль.
     */
    private function sectionCodeSet(array $sections): array
    {
        $sectionCodes = [];
        $rootMarks = [];
        foreach ($sections as $section) {
            $code = $section->getCode();
            if (isset($sectionCodes[$code])) {
                throw new RuleSpaceInvalidException('Catalog section code is duplicated');
            }

            $sectionCodes[$code] = true;
            $rootMark = $section->getCatalogRootFor();
            if ($rootMark !== null) {
                if (isset($rootMarks[$rootMark])) {
                    throw new RuleSpaceInvalidException('Catalog root mark is duplicated');
                }

                $rootMarks[$rootMark] = true;
            }
        }

        return $sectionCodes;
    }

    /**
     * parent_code в дереве, без цикла.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     * @param array<string, true> $sectionCodes Коды.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если родитель или цикл.
     */
    private function assertParents(array $sections, array $sectionCodes): void
    {
        $parentByCode = [];
        foreach ($sections as $section) {
            $parentCode = $section->getParentCode();
            if ($parentCode === null) {
                continue;
            }

            if (!isset($sectionCodes[$parentCode])) {
                throw new RuleSpaceInvalidException('Catalog parent is unknown');
            }

            $parentByCode[$section->getCode()] = $parentCode;
        }

        foreach (array_keys($parentByCode) as $code) {
            $this->assertNoCycle($code, $parentByCode);
        }
    }

    /**
     * Обход предков одного узла.
     *
     * @param string $startCode Узел.
     * @param array<string, string> $parentByCode Ребра.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если цикл.
     */
    private function assertNoCycle(string $startCode, array $parentByCode): void
    {
        $seen = [];
        $current = $startCode;
        while (isset($parentByCode[$current])) {
            if (isset($seen[$current])) {
                throw new RuleSpaceInvalidException('Catalog parent cycle');
            }

            $seen[$current] = true;
            $current = $parentByCode[$current];
        }
    }

    /**
     * Размещения: известная секция, одно правило на снимок.
     *
     * @param array<int, RuleSpaceCatalogPlacement> $placements Карточки.
     * @param array<string, true> $sectionCodes Коды.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если секция или дубль правила.
     */
    private function assertPlacements(array $placements, array $sectionCodes): void
    {
        $ruleCodes = [];
        foreach ($placements as $placement) {
            if (!isset($sectionCodes[$placement->getSectionCode()])) {
                throw new RuleSpaceInvalidException('Catalog section is unknown');
            }

            $ruleCode = $placement->getRuleCode();
            if (isset($ruleCodes[$ruleCode])) {
                throw new RuleSpaceInvalidException('Catalog rule is placed twice');
            }

            $ruleCodes[$ruleCode] = true;
        }
    }
}
