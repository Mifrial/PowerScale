<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto;

use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

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

        self::assertSnapshot($sections, $placements);

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
    private static function assertSnapshot(array $sections, array $placements): void
    {
        if (count($sections) > 500 || count($placements) > 10000) {
            throw new RuleSpaceInvalidException('Catalog snapshot is too large');
        }

        $sectionCodes = self::collectSectionCodes($sections);
        self::assertParents($sections, $sectionCodes);
        self::assertPlacements($placements, $sectionCodes);
    }

    /**
     * Собирает уникальные коды узлов.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     *
     * @return array<string, true> Коды.
     *
     * @throws RuleSpaceInvalidException Если дубль.
     */
    private static function collectSectionCodes(array $sections): array
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
     * Проверяет parent_code в дереве, без цикла.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Узлы.
     * @param array<string, true> $sectionCodes Коды.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если родитель или цикл.
     */
    private static function assertParents(array $sections, array $sectionCodes): void
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
            self::assertNoCycle($code, $parentByCode);
        }
    }

    /**
     * Проверяет обход предков одного узла.
     *
     * @param string $startCode Узел.
     * @param array<string, string> $parentByCode Ребра.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если цикл.
     */
    private static function assertNoCycle(string $startCode, array $parentByCode): void
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
     * Проверяет размещения: известная секция, одно правило на снимок.
     *
     * @param array<int, RuleSpaceCatalogPlacement> $placements Карточки.
     * @param array<string, true> $sectionCodes Коды.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если секция или дубль правила.
     */
    private static function assertPlacements(array $placements, array $sectionCodes): void
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
