<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSummary;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;

/**
 * Record и срез → JSON HTTP.
 */
final class RuleSpaceViewAssembler
{
    /**
     * Собирает JSON-вид мира.
     *
     * @param RuleSpaceRecord $ruleSpaceRecord Мир.
     * @param array<int, RuleRevisionSummary> $revisionSummaries Лента DESC.
     *
     * @return array<string, mixed> Space.
     */
    public function assembleSpace(RuleSpaceRecord $ruleSpaceRecord, array $revisionSummaries): array
    {
        $latest = $revisionSummaries[0] ?? null;

        return [
            'id' => $ruleSpaceRecord->getId(),
            'code' => $ruleSpaceRecord->getCode(),
            'name' => $ruleSpaceRecord->getName(),
            'description' => $ruleSpaceRecord->getDescription(),
            'ownerId' => $ruleSpaceRecord->getOwnerId(),
            'revision' => $latest instanceof RuleRevisionSummary ? $latest->getRevision() : 0,
            'active' => $ruleSpaceRecord->isActive(),
            'createdAt' => $ruleSpaceRecord->getCreatedAt()->toUnix(),
            'rulesCount' => $latest instanceof RuleRevisionSummary ? $latest->getRuleCount() : 0,
        ];
    }

    /**
     * JSON ленты без changedCount.
     *
     * @param RuleRevisionSummary $revisionSummary Сводка.
     *
     * @return array<string, mixed> Meta.
     */
    public function assembleRevisionMeta(RuleRevisionSummary $revisionSummary): array
    {
        return [
            'revision' => $revisionSummary->getRevision(),
            'publishedAt' => $revisionSummary->getPublishedAt()->toUnix(),
            'ruleCount' => $revisionSummary->getRuleCount(),
        ];
    }

    /**
     * JSON среза ревизии.
     *
     * @param RuleSpaceRecord $ruleSpaceRecord Мир.
     * @param RuleRevisionSlice $revisionSlice Срез.
     * @param RuleSpaceCatalog $catalog Снимок секций.
     *
     * @return array<string, mixed> SpaceRevision.
     */
    public function assembleRevision(
        RuleSpaceRecord $ruleSpaceRecord,
        RuleRevisionSlice $revisionSlice,
        RuleSpaceCatalog $catalog,
    ): array {
        $revisionRecord = $revisionSlice->getRevision();
        $jsonMapper = new RuleSpaceCatalogJsonMapper();
        $placements = $jsonMapper->placementsByRule($catalog);
        $rules = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $rules[] = $this->assembleRule(
                $ruleSpaceRecord->getId(),
                $versionRecord,
                $placements[$versionRecord->getCode()] ?? null,
            );
        }

        return [
            'revision' => $revisionRecord->getRevision(),
            'publishedAt' => $revisionRecord->getPublishedAt()->toUnix(),
            'spaceCode' => $ruleSpaceRecord->getCode(),
            'spaceName' => $ruleSpaceRecord->getName(),
            'sections' => $jsonMapper->assembleSections($catalog),
            'rules' => $rules,
        ];
    }

    /**
     * JSON правила в срезе.
     *
     * @param int $spaceId Мир.
     * @param RuleVersionRecord $versionRecord Снимок.
     * @param RuleSpaceCatalogPlacement|null $placement Карточка.
     *
     * @return array<string, mixed> Rule.
     */
    public function assembleRule(
        int $spaceId,
        RuleVersionRecord $versionRecord,
        ?RuleSpaceCatalogPlacement $placement = null,
    ): array {
        $view = [
            'id' => $versionRecord->getVersionId(),
            'code' => $versionRecord->getCode(),
            'type' => $versionRecord->getType(),
            'name' => $versionRecord->getName(),
            'description' => $versionRecord->getDescription(),
            'spaceId' => $spaceId,
            'spec' => $versionRecord->getSpec(),
            'keywordIds' => $versionRecord->getKeywordIds(),
            'mechanicId' => $versionRecord->getMechanicId(),
            'mechanicPayload' => $versionRecord->getMechanicPayload(),
            'contentStatus' => $versionRecord->getContentStatus(),
            'contentNote' => $versionRecord->getContentNote(),
            'active' => $versionRecord->isActive(),
            'createdAt' => $versionRecord->getCreatedAt()->toUnix(),
            'catalogSection' => $placement?->getSectionCode(),
        ];
        if ($placement instanceof RuleSpaceCatalogPlacement) {
            $view['catalogSortOrder'] = $placement->getSortOrder();
        }

        return $view;
    }
}
