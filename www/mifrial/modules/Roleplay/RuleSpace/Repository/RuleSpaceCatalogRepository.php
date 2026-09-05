<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Repository;

use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;

/**
 * Снимки секций и указатели ревизий.
 */
final class RuleSpaceCatalogRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $sectionRecords Узлы.
     * @param IOpenedRecords $itemRecords Размещения.
     * @param IOpenedRecords $pointerRecords Указатели.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $sectionRecords,
        private readonly IOpenedRecords $itemRecords,
        private readonly IOpenedRecords $pointerRecords,
    ) {
    }

    /**
     * Снимок ревизии или пустой, если указателя нет.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер часов.
     *
     * @return RuleSpaceCatalog Снимок.
     *
     * @throws RuleSpaceInvalidException Если строки битые.
     */
    public function getByRevision(int $spaceId, int $revision): RuleSpaceCatalog
    {
        $pointer = $this->pointerRecords->getFirst(ListQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId, '=revision' => $revision],
            'limit' => 1,
        ]));
        if ($pointer === null) {
            return RuleSpaceCatalog::empty();
        }

        $sectionVersion = (int) $pointer['section_version'];

        return $this->getBySectionVersion($spaceId, $sectionVersion);
    }

    /**
     * Снимок по номеру каталога.
     *
     * @param int $spaceId Мир.
     * @param int $sectionVersion Номер снимка.
     *
     * @return RuleSpaceCatalog Снимок.
     *
     * @throws RuleSpaceInvalidException Если строки битые.
     */
    public function getBySectionVersion(int $spaceId, int $sectionVersion): RuleSpaceCatalog
    {
        $sectionRows = $this->sectionRecords->getList(ListQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId, '=section_version' => $sectionVersion],
            'sort' => ['sort_order' => 'asc', 'code' => 'asc'],
            'limit' => ListQuery::MAX_LIMIT,
        ]))->rows();
        $sections = [];
        $sectionIds = [];
        $codeById = [];
        foreach ($sectionRows as $sectionRow) {
            $sectionId = (int) $sectionRow['id'];
            $section = $this->sectionFromRow($sectionRow);
            $sections[] = $section;
            $sectionIds[] = $sectionId;
            $codeById[$sectionId] = $section->getCode();
        }

        return RuleSpaceCatalog::fromParts($sections, $this->placementsForSections($sectionIds, $codeById));
    }

    /**
     * Номер снимка latest-указателя или null.
     *
     * @param int $spaceId Мир.
     *
     * @return int|null section_version.
     */
    public function findLatestSectionVersion(int $spaceId): ?int
    {
        $pointer = $this->pointerRecords->getFirst(ListQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId],
            'sort' => ['revision' => 'desc'],
            'limit' => 1,
        ]));
        if ($pointer === null) {
            return null;
        }

        return (int) $pointer['section_version'];
    }

    /**
     * Следующий номер снимка: 1 + max указателей.
     *
     * @param int $spaceId Мир.
     *
     * @return int Номер ≥1.
     */
    public function nextSectionVersion(int $spaceId): int
    {
        $rows = $this->pointerRecords->aggregate(AggregateQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId],
            'group' => ['space_id'],
            'select' => ['space_id', new MaxField('section_version', 'max_section_version')],
            'limit' => 1,
        ]))->rows();
        if ($rows === []) {
            return 1;
        }

        return ((int) $rows[0]['max_section_version']) + 1;
    }

    /**
     * Пишет узлы и item нового снимка. Пустой список узлов — только номер.
     *
     * @param int $spaceId Мир.
     * @param int $sectionVersion Номер.
     * @param RuleSpaceCatalog $catalog Снимок.
     *
     * @return void
     */
    public function insertSnapshot(int $spaceId, int $sectionVersion, RuleSpaceCatalog $catalog): void
    {
        $sections = $catalog->getSections();
        if ($sections === []) {
            return;
        }

        $sectionRows = [];
        foreach ($sections as $section) {
            $sectionRows[] = [
                'space_id' => $spaceId,
                'section_version' => $sectionVersion,
                'code' => $section->getCode(),
                'name' => $section->getName(),
                'parent_code' => $section->getParentCode(),
                'sort_order' => $section->getSortOrder(),
                'catalog_root_for' => $section->getCatalogRootFor(),
            ];
        }

        $sectionIds = $this->sectionRecords->addMany($sectionRows);
        $idByCode = [];
        foreach ($sections as $index => $section) {
            $idByCode[$section->getCode()] = $sectionIds[$index];
        }

        $this->insertItems($catalog, $idByCode);
    }

    /**
     * Копия снимка в другой мир: новые PK, те же code.
     *
     * @param int $sourceSpaceId Родитель.
     * @param int $sourceSectionVersion Снимок родителя.
     * @param int $targetSpaceId Ребёнок.
     * @param int $targetSectionVersion Номер у ребёнка.
     *
     * @return void
     */
    public function copySnapshot(
        int $sourceSpaceId,
        int $sourceSectionVersion,
        int $targetSpaceId,
        int $targetSectionVersion,
    ): void {
        $this->insertSnapshot(
            $targetSpaceId,
            $targetSectionVersion,
            $this->getBySectionVersion($sourceSpaceId, $sourceSectionVersion),
        );
    }

    /**
     * Insert указателя. Не update.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер часов.
     * @param int $sectionVersion Снимок.
     *
     * @return void
     */
    public function insertPointer(int $spaceId, int $revision, int $sectionVersion): void
    {
        $this->pointerRecords->add([
            'space_id' => $spaceId,
            'revision' => $revision,
            'section_version' => $sectionVersion,
        ]);
    }

    /**
     * Item из id узлов.
     *
     * @param array<int, int> $sectionIds PK узлов.
     * @param array<int, string> $codeById code по id.
     *
     * @return array<int, RuleSpaceCatalogPlacement> Карточки.
     */
    private function placementsForSections(array $sectionIds, array $codeById): array
    {
        if ($sectionIds === []) {
            return [];
        }

        $placements = [];
        foreach ($sectionIds as $sectionId) {
            foreach (
                $this->itemRecords->getList(ListQuery::fromOptions([
                    'filter' => ['=section_id' => $sectionId],
                    'sort' => ['sort_order' => 'asc', 'rule_code' => 'asc'],
                    'limit' => ListQuery::MAX_LIMIT,
                ]))->rows() as $itemRow
            ) {
                $placements[] = new RuleSpaceCatalogPlacement(
                    (string) $itemRow['rule_code'],
                    $codeById[$sectionId],
                    (int) $itemRow['sort_order'],
                );
            }
        }

        return $placements;
    }

    /**
     * Item нового снимка.
     *
     * @param RuleSpaceCatalog $catalog Снимок.
     * @param array<string, int> $idByCode PK узлов.
     *
     * @return void
     */
    private function insertItems(RuleSpaceCatalog $catalog, array $idByCode): void
    {
        $itemRows = [];
        foreach ($catalog->getPlacements() as $placement) {
            $itemRows[] = [
                'section_id' => $idByCode[$placement->getSectionCode()],
                'rule_code' => $placement->getRuleCode(),
                'sort_order' => $placement->getSortOrder(),
            ];
        }

        if ($itemRows !== []) {
            $this->itemRecords->addMany($itemRows);
        }
    }

    /**
     * Узел из строки.
     *
     * @param array<string, mixed> $sectionRow Колонки.
     *
     * @return RuleSpaceCatalogSection Узел.
     */
    private function sectionFromRow(array $sectionRow): RuleSpaceCatalogSection
    {
        $parentCode = $sectionRow['parent_code'] ?? null;
        $rootMark = $sectionRow['catalog_root_for'] ?? null;

        return new RuleSpaceCatalogSection(
            (string) $sectionRow['code'],
            (string) $sectionRow['name'],
            is_string($parentCode) ? $parentCode : null,
            (int) $sectionRow['sort_order'],
            is_string($rootMark) ? $rootMark : null,
        );
    }
}
