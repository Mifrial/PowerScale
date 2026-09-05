<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Versioning\Space\Dto\CommitEntry;
use Mifrial\Versioning\Space\Dto\PreparedComposition;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Keep/create/change → id версий и ряды до записи ревизии.
 */
final class CommitComposition
{
    /**
     * Создаёт сборщик состава.
     *
     * @param OpenedCluster $openedCluster Кластер.
     *
     * @return void
     */
    public function __construct(
        private readonly OpenedCluster $openedCluster,
    ) {
    }

    /**
     * Проверяет list CommitEntry.
     *
     * @param array<int|string, mixed> $entries Вход commit.
     *
     * @return array<int, CommitEntry> Пункты.
     *
     * @throws SpaceInvalidException Если форма неверна.
     */
    public function parseEntries(array $entries): array
    {
        if (!array_is_list($entries) || $entries === [] || count($entries) > ListQuery::MAX_LIMIT) {
            throw new SpaceInvalidException('Commit entries are invalid');
        }

        foreach ($entries as $entry) {
            if (!$entry instanceof CommitEntry) {
                throw new SpaceInvalidException('Commit entries are invalid');
            }
        }

        return $entries;
    }

    /**
     * Собирает состав: пишет identity и новые версии.
     *
     * @param array<int, CommitEntry> $entries Пункты.
     *
     * @return PreparedComposition Состав.
     *
     * @throws SpaceInvalidException Если keep/change недопустимы.
     */
    public function assemble(array $entries): PreparedComposition
    {
        $keepRows = $this->loadRowsById(
            $this->openedCluster->getVersions(),
            $this->collectKeepIds($entries),
        );
        $claimedEntities = [];
        $orderedIds = [];
        $createIndexes = [];
        $changeIndexes = [];
        foreach ($entries as $entryIndex => $entry) {
            $this->placeEntry(
                $entry,
                $entryIndex,
                $keepRows,
                $claimedEntities,
                $orderedIds,
                $createIndexes,
                $changeIndexes,
            );
        }

        $this->assertChangeIdentities($entries, $changeIndexes);
        $newRows = $this->insertNewVersions($entries, $orderedIds, $createIndexes, $changeIndexes);

        return new PreparedComposition($orderedIds, $keepRows + $newRows);
    }

    /**
     * Id keep-пунктов.
     *
     * @param array<int, CommitEntry> $entries Пункты.
     *
     * @return array<int, int> Id версий.
     */
    private function collectKeepIds(array $entries): array
    {
        $keepIds = [];
        foreach ($entries as $entry) {
            $versionId = $entry->getVersionId();
            if ($versionId !== null) {
                $keepIds[] = $versionId;
            }
        }

        return $keepIds;
    }

    /**
     * Раскладывает пункт в слот состава.
     *
     * @param CommitEntry $entry Пункт.
     * @param int $entryIndex Индекс.
     * @param array<int, array<string, mixed>> $keepRows Keep-ряды.
     * @param array<int, true> $claimedEntities Занятые identity.
     * @param array<int, int> $orderedIds Слоты.
     * @param array<int, int> $createIndexes Индексы create.
     * @param array<int, int> $changeIndexes Индексы change.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если keep нет или identity занята.
     */
    private function placeEntry(
        CommitEntry $entry,
        int $entryIndex,
        array $keepRows,
        array &$claimedEntities,
        array &$orderedIds,
        array &$createIndexes,
        array &$changeIndexes,
    ): void {
        $versionId = $entry->getVersionId();
        if ($versionId !== null) {
            $this->placeKeep($versionId, $keepRows, $claimedEntities, $orderedIds);

            return;
        }

        $orderedIds[] = 0;
        if ($entry->getEntityId() === null) {
            $createIndexes[] = $entryIndex;

            return;
        }

        $this->claimEntity($claimedEntities, $entry->getEntityId());
        $changeIndexes[] = $entryIndex;
    }

    /**
     * Keep-слот.
     *
     * @param int $versionId Id версии.
     * @param array<int, array<string, mixed>> $keepRows Ряды.
     * @param array<int, true> $claimedEntities Занятые identity.
     * @param array<int, int> $orderedIds Слоты.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если версии нет.
     */
    private function placeKeep(
        int $versionId,
        array $keepRows,
        array &$claimedEntities,
        array &$orderedIds,
    ): void {
        if (!isset($keepRows[$versionId])) {
            throw new SpaceInvalidException('Keep version is missing');
        }

        $entityId = $keepRows[$versionId]['entity_id'];
        if (!is_int($entityId)) {
            throw new SpaceInvalidException('Keep version is missing');
        }

        $this->claimEntity($claimedEntities, $entityId);
        $orderedIds[] = $versionId;
    }

    /**
     * Одна identity в составе.
     *
     * @param array<int, true> $claimedEntities Занятые.
     * @param int $entityId Identity.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если уже занята.
     */
    private function claimEntity(array &$claimedEntities, int $entityId): void
    {
        if (isset($claimedEntities[$entityId])) {
            throw new SpaceInvalidException('Identity appears twice');
        }

        $claimedEntities[$entityId] = true;
    }

    /**
     * Change ссылается на существующие identity.
     *
     * @param array<int, CommitEntry> $entries Пункты.
     * @param array<int, int> $changeIndexes Индексы.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если identity нет.
     */
    private function assertChangeIdentities(array $entries, array $changeIndexes): void
    {
        $entityIds = [];
        foreach ($changeIndexes as $entryIndex) {
            $entityId = $entries[$entryIndex]->getEntityId();
            if ($entityId !== null) {
                $entityIds[] = $entityId;
            }
        }

        if ($entityIds === []) {
            return;
        }

        $this->loadRowsById($this->openedCluster->getIdentities(), $entityIds);
    }

    /**
     * Пишет create/change версии и подставляет id.
     *
     * @param array<int, CommitEntry> $entries Пункты.
     * @param array<int, int> $orderedIds Слоты.
     * @param array<int, int> $createIndexes Create.
     * @param array<int, int> $changeIndexes Change.
     *
     * @return array<int, array<string, mixed>> Новые ряды.
     */
    private function insertNewVersions(
        array $entries,
        array &$orderedIds,
        array $createIndexes,
        array $changeIndexes,
    ): array {
        $versionMaps = $this->buildVersionMaps($entries, $createIndexes, $changeIndexes);
        if ($versionMaps === []) {
            return [];
        }

        $versionIds = $this->openedCluster->getVersions()->addMany($versionMaps);
        $slotIndexes = array_merge($createIndexes, $changeIndexes);
        foreach ($slotIndexes as $cursor => $entryIndex) {
            $orderedIds[$entryIndex] = $versionIds[$cursor];
        }

        return $this->loadRowsById($this->openedCluster->getVersions(), $versionIds);
    }

    /**
     * Карты insert новых версий.
     *
     * @param array<int, CommitEntry> $entries Пункты.
     * @param array<int, int> $createIndexes Create.
     * @param array<int, int> $changeIndexes Change.
     *
     * @return array<int, array<string, mixed>> Ряды.
     */
    private function buildVersionMaps(array $entries, array $createIndexes, array $changeIndexes): array
    {
        $entityIds = [];
        if ($createIndexes !== []) {
            $identityMaps = [];
            foreach ($createIndexes as $entryIndex) {
                $identityMaps[] = $entries[$entryIndex]->getIdentityFields();
            }

            $entityIds = $this->openedCluster->getIdentities()->addMany($identityMaps);
        }

        $versionMaps = [];
        foreach ($createIndexes as $cursor => $entryIndex) {
            $versionMaps[] = $this->versionValues($entityIds[$cursor], $entries[$entryIndex]);
        }

        foreach ($changeIndexes as $entryIndex) {
            $entityId = $entries[$entryIndex]->getEntityId();
            if ($entityId !== null) {
                $versionMaps[] = $this->versionValues($entityId, $entries[$entryIndex]);
            }
        }

        return $versionMaps;
    }

    /**
     * Поля новой версии без created_at.
     *
     * @param int $entityId Identity.
     * @param CommitEntry $entry Пункт.
     *
     * @return array<string, mixed> Карта.
     */
    private function versionValues(int $entityId, CommitEntry $entry): array
    {
        return [
            'entity_id' => $entityId,
            'active' => $entry->isActive(),
            ...$entry->getVersionFields(),
        ];
    }

    /**
     * Строки по id; пустой список — без IN.
     *
     * @param IOpenedRecords $openedRecords Таблица.
     * @param array<int, int> $rowIds Id.
     *
     * @return array<int, array<string, mixed>> Ряды по id.
     *
     * @throws SpaceInvalidException Если набора нет.
     */
    private function loadRowsById(IOpenedRecords $openedRecords, array $rowIds): array
    {
        if ($rowIds === []) {
            return [];
        }

        $uniqueIds = array_values(array_unique($rowIds));
        $rowsById = [];
        foreach (
            $openedRecords->getList(ListQuery::fromOptions([
                'filter' => ['id' => $uniqueIds],
                'limit' => ListQuery::MAX_LIMIT,
            ]))->rows() as $row
        ) {
            if (is_int($row['id'] ?? null)) {
                $rowsById[$row['id']] = $row;
            }
        }

        if (count($rowsById) !== count($uniqueIds)) {
            throw new SpaceInvalidException('Referenced rows are missing');
        }

        return $rowsById;
    }
}
