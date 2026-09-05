<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSlice;
use Mifrial\Versioning\Space\Dto\VersionRecord;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use Mifrial\Versioning\Space\Exception\SpaceNotFoundException;

/**
 * Срез из MySQL по (spaceId, revision).
 */
final class RevisionLoader
{
    /**
     * Создаёт загрузчик.
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
     * Читает ревизию, состав и версии.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     *
     * @return RevisionSlice Срез.
     *
     * @throws SpaceNotFoundException Если ревизии нет.
     * @throws SpaceInvalidException Если состав пуст или версии не сходятся.
     */
    public function load(int $spaceId, int $revision): RevisionSlice
    {
        $revisionRow = $this->openedCluster->getRevisions()->getUnique(ListQuery::fromOptions([
            'filter' => [
                '=space_id' => $spaceId,
                '=revision' => $revision,
            ],
            'limit' => 1,
        ]));
        if ($revisionRow === null) {
            throw new SpaceNotFoundException('Revision was not found');
        }

        $revisionRecord = RevisionRecord::fromNormalized($revisionRow);
        $itemRows = $this->openedCluster->getItems()->getList(ListQuery::fromOptions([
            'filter' => ['=revision_id' => $revisionRecord->getId()],
            'sort' => ['id' => 'asc'],
            'limit' => ListQuery::MAX_LIMIT,
        ]))->rows();
        if ($itemRows === []) {
            throw new SpaceInvalidException('Revision composition is empty');
        }

        return new RevisionSlice($revisionRecord, $this->itemsInOrder($itemRows));
    }

    /**
     * Склеивает версии по порядку пунктов состава.
     *
     * @param array<int, array<string, mixed>> $itemRows Пункты.
     *
     * @return array<int, VersionRecord> Экземпляры.
     *
     * @throws SpaceInvalidException Если version нет.
     */
    private function itemsInOrder(array $itemRows): array
    {
        $versionIds = [];
        foreach ($itemRows as $itemRow) {
            if (is_int($itemRow['version_id'] ?? null)) {
                $versionIds[] = $itemRow['version_id'];
            }
        }

        if ($versionIds === []) {
            throw new SpaceInvalidException('Composition version is missing');
        }

        $rowsById = [];
        foreach (
            $this->openedCluster->getVersions()->getList(ListQuery::fromOptions([
                'filter' => ['id' => $versionIds],
                'limit' => ListQuery::MAX_LIMIT,
            ]))->rows() as $versionRow
        ) {
            if (is_int($versionRow['id'] ?? null)) {
                $rowsById[$versionRow['id']] = $versionRow;
            }
        }

        return $this->mapItems($itemRows, $rowsById);
    }

    /**
     * Пункты в порядке состава.
     *
     * @param array<int, array<string, mixed>> $itemRows Пункты.
     * @param array<int, array<string, mixed>> $rowsById Версии.
     *
     * @return array<int, VersionRecord> Экземпляры.
     *
     * @throws SpaceInvalidException Если version нет.
     */
    private function mapItems(array $itemRows, array $rowsById): array
    {
        $items = [];
        foreach ($itemRows as $itemRow) {
            $versionId = $itemRow['version_id'] ?? null;
            if (!is_int($versionId) || !isset($rowsById[$versionId])) {
                throw new SpaceInvalidException('Composition version is missing');
            }

            $items[] = VersionRecord::fromNormalized($rowsById[$versionId]);
        }

        return $items;
    }
}
