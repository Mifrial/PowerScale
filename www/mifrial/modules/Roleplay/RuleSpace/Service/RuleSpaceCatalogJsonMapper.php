<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * JSON секций и размещений commitDraft / срез.
 */
final class RuleSpaceCatalogJsonMapper
{
    /**
     * Дерево из JSON list.
     *
     * @param mixed $sections JSON.
     *
     * @return array<int, RuleSpaceCatalogSection> Узлы.
     *
     * @throws RuleSpaceInvalidException Если форма.
     */
    public function mapSections(mixed $sections): array
    {
        if (!is_array($sections) || !array_is_list($sections)) {
            throw new RuleSpaceInvalidException('Catalog sections must be a list');
        }

        $mapped = [];
        foreach ($sections as $sectionRow) {
            $mapped[] = $this->mapSection($sectionRow);
        }

        return $mapped;
    }

    /**
     * Размещение put: нет ключа или null — без карточки.
     *
     * @param array<string|int, mixed> $ruleRow JSON правила.
     *
     * @return RuleSpaceCatalogPlacement|null Карточка.
     *
     * @throws RuleSpaceInvalidException Если поля.
     */
    public function mapPutPlacement(array $ruleRow): ?RuleSpaceCatalogPlacement
    {
        if (!array_key_exists('catalogSection', $ruleRow) || $ruleRow['catalogSection'] === null) {
            return null;
        }

        $sectionCode = $ruleRow['catalogSection'];
        $code = $ruleRow['code'] ?? null;
        if (!is_string($sectionCode) || !is_string($code)) {
            throw new RuleSpaceInvalidException('Catalog placement is invalid');
        }

        $sortOrder = $ruleRow['catalogSortOrder'] ?? 0;
        if (!is_int($sortOrder)) {
            throw new RuleSpaceInvalidException('Catalog placement is invalid');
        }

        return new RuleSpaceCatalogPlacement(trim($code), trim($sectionCode), $sortOrder);
    }

    /**
     * JSON узлов среза.
     *
     * @param RuleSpaceCatalog $catalog Снимок.
     *
     * @return array<int, array<string, mixed>> sections.
     */
    public function assembleSections(RuleSpaceCatalog $catalog): array
    {
        $views = [];
        foreach ($catalog->getSections() as $section) {
            $view = [
                'code' => $section->getCode(),
                'name' => $section->getName(),
                'parentCode' => $section->getParentCode(),
                'sortOrder' => $section->getSortOrder(),
            ];
            if ($section->getCatalogRootFor() !== null) {
                $view['catalogRootFor'] = $section->getCatalogRootFor();
            }

            $views[] = $view;
        }

        return $views;
    }

    /**
     * Размещения по code правила.
     *
     * @param RuleSpaceCatalog $catalog Снимок.
     *
     * @return array<string, RuleSpaceCatalogPlacement> Ключ = rule_code.
     */
    public function placementsByRule(RuleSpaceCatalog $catalog): array
    {
        $placements = [];
        foreach ($catalog->getPlacements() as $placement) {
            $placements[$placement->getRuleCode()] = $placement;
        }

        return $placements;
    }

    /**
     * Один узел.
     *
     * @param mixed $sectionRow JSON.
     *
     * @return RuleSpaceCatalogSection Узел.
     *
     * @throws RuleSpaceInvalidException Если поля.
     */
    private function mapSection(mixed $sectionRow): RuleSpaceCatalogSection
    {
        if (!is_array($sectionRow)) {
            throw new RuleSpaceInvalidException('Catalog section must be an object');
        }

        $code = $this->requiredTrimmed($sectionRow, 'code');
        $name = $this->requiredTrimmed($sectionRow, 'name');
        $sortOrder = $sectionRow['sortOrder'] ?? null;
        if (!is_int($sortOrder)) {
            throw new RuleSpaceInvalidException('Catalog section sortOrder is invalid');
        }

        return new RuleSpaceCatalogSection(
            $code,
            $name,
            $this->optionalParent($sectionRow),
            $sortOrder,
            $this->optionalRoot($sectionRow),
        );
    }

    /**
     * Читает необязательный код родителя.
     *
     * @param array<string|int, mixed> $sectionRow JSON.
     *
     * @return string|null Код.
     *
     * @throws RuleSpaceInvalidException Если не строка.
     */
    private function optionalParent(array $sectionRow): ?string
    {
        if (!array_key_exists('parentCode', $sectionRow) || $sectionRow['parentCode'] === null) {
            return null;
        }

        $parentCode = $sectionRow['parentCode'];
        if (!is_string($parentCode)) {
            throw new RuleSpaceInvalidException('Catalog parentCode is invalid');
        }

        $trimmed = trim($parentCode);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Читает необязательную метку корня.
     *
     * @param array<string|int, mixed> $sectionRow JSON.
     *
     * @return string|null Метка.
     *
     * @throws RuleSpaceInvalidException Если не строка.
     */
    private function optionalRoot(array $sectionRow): ?string
    {
        if (!array_key_exists('catalogRootFor', $sectionRow) || $sectionRow['catalogRootFor'] === null) {
            return null;
        }

        $rootMark = $sectionRow['catalogRootFor'];
        if (!is_string($rootMark)) {
            throw new RuleSpaceInvalidException('Catalog root is invalid');
        }

        $trimmed = trim($rootMark);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Обязательная непустая строка.
     *
     * @param array<string|int, mixed> $row JSON.
     * @param string $fieldName Ключ.
     *
     * @return string Trim.
     *
     * @throws RuleSpaceInvalidException Если пусто.
     */
    private function requiredTrimmed(array $row, string $fieldName): string
    {
        $value = $row[$fieldName] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuleSpaceInvalidException('Catalog section field is invalid');
        }

        return trim($value);
    }
}
