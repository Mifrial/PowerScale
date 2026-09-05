<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogPlacement;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalogSection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Сборка снимка для commitDraft: put + omit-keep ∩ новое дерево.
 */
final class RuleSpaceCatalogDraftComposer
{
    /**
     * Снимок из дерева и размещений.
     *
     * @param array<int, RuleSpaceCatalogSection> $sections Новое дерево.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, array<string|int, mixed>> $putRows JSON put в том же порядке.
     * @param RuleSpaceCatalog $latestCatalog Latest.
     * @param array<string, true> $omitKeepCodes omit-keep и tombstone.
     *
     * @return RuleSpaceCatalog Снимок.
     *
     * @throws RuleSpaceInvalidException Если инвариант.
     */
    public function compose(
        array $sections,
        array $puts,
        array $putRows,
        RuleSpaceCatalog $latestCatalog,
        array $omitKeepCodes,
    ): RuleSpaceCatalog {
        $jsonMapper = new RuleSpaceCatalogJsonMapper();
        $sectionCodes = [];
        foreach ($sections as $section) {
            $sectionCodes[$section->getCode()] = true;
        }

        $placements = $this->putPlacements($puts, $putRows, $jsonMapper);
        $putCodes = $this->putCodeSet($puts);
        foreach ($latestCatalog->getPlacements() as $placement) {
            $ruleCode = $placement->getRuleCode();
            if (isset($putCodes[$ruleCode]) || !isset($omitKeepCodes[$ruleCode])) {
                continue;
            }

            if (!isset($sectionCodes[$placement->getSectionCode()])) {
                continue;
            }

            $placements[] = $placement;
        }

        return RuleSpaceCatalog::fromParts($sections, $placements);
    }

    /**
     * Размещения put.
     *
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, array<string|int, mixed>> $putRows JSON.
     * @param RuleSpaceCatalogJsonMapper $jsonMapper Разбор.
     *
     * @return array<int, RuleSpaceCatalogPlacement> Карточки.
     *
     * @throws RuleSpaceInvalidException Если поля.
     */
    private function putPlacements(
        array $puts,
        array $putRows,
        RuleSpaceCatalogJsonMapper $jsonMapper,
    ): array {
        $placements = [];
        foreach ($puts as $index => $putEntry) {
            $ruleRow = $putRows[$index] ?? [];
            if (!is_array($ruleRow)) {
                continue;
            }

            $placement = $jsonMapper->mapPutPlacement($ruleRow);
            if ($placement instanceof RuleSpaceCatalogPlacement) {
                $placements[] = $placement;
            }
        }

        return $placements;
    }

    /**
     * Коды put.
     *
     * @param array<int, RuleCommitEntry> $puts Put.
     *
     * @return array<string, true> Коды.
     */
    private function putCodeSet(array $puts): array
    {
        $putCodes = [];
        foreach ($puts as $putEntry) {
            $code = $putEntry->getCode();
            if ($code !== null) {
                $putCodes[$code] = true;
            }
        }

        return $putCodes;
    }
}
