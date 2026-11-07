<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceConflictException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

/**
 * Публикация черновика: каталог, полный первый put и выбор поверх головы.
 */
final class RuleSpaceDraftPublisher
{
    /**
     * Создаёт публикатор.
     *
     * @param IRuleSpaces $ruleSpaces Оператор мира.
     *
     * @return void
     */
    public function __construct(
        private readonly IRuleSpaces $ruleSpaces,
    ) {
    }

    /**
     * Публикует черновик и возвращает номер новой ревизии.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, string> $removedCodes Tombstone.
     * @param OptionalArray $sections Ключ JSON sections.
     * @param array<int, mixed> $putRows JSON rules.
     * @param int $expectedRevision Ревизия черновика.
     *
     * @return int Номер новой ревизии.
     *
     * @throws RuleSpaceInvalidException Если состав недопустим.
     * @throws RuleSpaceConflictException Если голова уже другая.
     * @throws RuleSpaceNotFoundException Если базы нет.
     */
    public function publish(
        int $spaceId,
        array $puts,
        array $removedCodes,
        OptionalArray $sections,
        array $putRows,
        int $expectedRevision,
    ): int {
        return $this->publishDraft(
            $spaceId,
            $puts,
            $removedCodes,
            $this->draftCatalog($spaceId, $sections, $putRows, $puts, $expectedRevision),
            $expectedRevision,
        );
    }

    /**
     * Первая ревизия — полный put; иначе выбор поверх latest.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, string> $removedCodes Tombstone.
     * @param RuleSpaceCatalog|null $catalog Снимок.
     * @param int $expectedRevision Ревизия черновика.
     *
     * @return int Номер новой ревизии.
     *
     * @throws RuleSpaceInvalidException Если состав недопустим.
     * @throws RuleSpaceConflictException Если голова уже другая.
     * @throws RuleSpaceNotFoundException Если базы нет.
     */
    private function publishDraft(
        int $spaceId,
        array $puts,
        array $removedCodes,
        ?RuleSpaceCatalog $catalog,
        int $expectedRevision,
    ): int {
        if ($expectedRevision === 0) {
            return $this->publishFirstDraft($spaceId, $puts, $removedCodes, $catalog, $expectedRevision);
        }

        if ($puts === [] && $removedCodes === [] && $catalog instanceof RuleSpaceCatalog) {
            $slice = $this->ruleSpaces->getRevision($spaceId, $expectedRevision);

            return $this->ruleSpaces->commit(
                $spaceId,
                $this->keepEntries($slice),
                $catalog,
                $expectedRevision,
            )->getRevision();
        }

        return $this->ruleSpaces->commitSelected(
            $spaceId,
            RuleSpaceSelection::fromParts($expectedRevision, $puts, $removedCodes),
            $catalog,
            $expectedRevision,
        )->getRevision();
    }

    /**
     * Первая ревизия: каталог-only недопустим.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param array<int, string> $removedCodes Tombstone.
     * @param RuleSpaceCatalog|null $catalog Снимок.
     * @param int $expectedRevision Ноль, если ревизий ещё нет.
     *
     * @return int Номер.
     *
     * @throws RuleSpaceInvalidException Если removed или пустой состав.
     * @throws RuleSpaceConflictException Если голова уже есть.
     */
    private function publishFirstDraft(
        int $spaceId,
        array $puts,
        array $removedCodes,
        ?RuleSpaceCatalog $catalog,
        int $expectedRevision,
    ): int {
        if ($removedCodes !== []) {
            throw new RuleSpaceInvalidException('Removed codes need a published revision');
        }

        if ($puts === [] && $catalog instanceof RuleSpaceCatalog) {
            throw new RuleSpaceInvalidException('Catalog-only needs a published revision');
        }

        return $this->ruleSpaces->commit($spaceId, $puts, $catalog, $expectedRevision)->getRevision();
    }

    /**
     * Снимок из JSON sections или null.
     *
     * @param int $spaceId Мир.
     * @param OptionalArray $sections Ключ JSON.
     * @param array<int, mixed> $putRows JSON rules.
     * @param array<int, RuleCommitEntry> $puts Put.
     * @param int $expectedRevision Ревизия черновика.
     *
     * @return RuleSpaceCatalog|null Снимок.
     *
     * @throws RuleSpaceInvalidException Если дерево.
     * @throws RuleSpaceNotFoundException Если базы нет.
     */
    private function draftCatalog(
        int $spaceId,
        OptionalArray $sections,
        array $putRows,
        array $puts,
        int $expectedRevision,
    ): ?RuleSpaceCatalog {
        if (!$sections->isPresent()) {
            return null;
        }

        $jsonMapper = new RuleSpaceCatalogJsonMapper();
        $mappedSections = $jsonMapper->mapSections($sections->getValue());
        $baseCatalog = RuleSpaceCatalog::empty();
        $omitKeepCodes = [];
        if ($expectedRevision > 0) {
            $slice = $this->ruleSpaces->getRevision($spaceId, $expectedRevision);
            $baseCatalog = $this->ruleSpaces->getCatalog($spaceId, $expectedRevision);
            $omitKeepCodes = $this->omitKeepCodes($slice, $puts);
        }

        return (new RuleSpaceCatalogDraftComposer())->compose(
            $mappedSections,
            $puts,
            $putRows,
            $baseCatalog,
            $omitKeepCodes,
        );
    }

    /**
     * Коды состава кроме put.
     *
     * @param RuleRevisionSlice $revisionSlice Latest.
     * @param array<int, RuleCommitEntry> $puts Put.
     *
     * @return array<string, true> Коды.
     */
    private function omitKeepCodes(RuleRevisionSlice $revisionSlice, array $puts): array
    {
        $putCodes = [];
        foreach ($puts as $putEntry) {
            $code = $putEntry->getCode();
            if ($code !== null) {
                $putCodes[$code] = true;
            }
        }

        $omitKeepCodes = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $code = $versionRecord->getCode();
            if (!isset($putCodes[$code])) {
                $omitKeepCodes[$code] = true;
            }
        }

        return $omitKeepCodes;
    }

    /**
     * Keep всего среза.
     *
     * @param RuleRevisionSlice $revisionSlice Состав.
     *
     * @return array<int, RuleCommitEntry> Keep.
     */
    private function keepEntries(RuleRevisionSlice $revisionSlice): array
    {
        $entries = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $entries[] = RuleCommitEntry::keep($versionRecord->getVersionId());
        }

        return $entries;
    }
}
