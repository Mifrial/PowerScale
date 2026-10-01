<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Repository;

use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Versioning\Space\Dto\PreparedComposition;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSlice;
use Mifrial\Versioning\Space\Dto\VersionRecord;
use Mifrial\Versioning\Space\Dto\WrittenRevision;
use Mifrial\Versioning\Space\Exception\SpaceConflictException;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;

/**
 * Номер ревизии, сравнение состава, insert часов.
 */
final class RevisionPublisher
{
    /**
     * Создаёт публикатор.
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
     * Пишет ревизию и пункты состава.
     *
     * @param int $spaceId Пространство.
     * @param PreparedComposition $preparedComposition Состав.
     * @param bool $allowUnchangedComposition Разрешить тот же набор version_id.
     *
     * @return WrittenRevision Ревизия и срез.
     *
     * @param int|null $expectedRevision Голова, от которой собран состав. null — прежняя нумерация MAX+1.
     *
     * @throws SpaceInvalidException Если состав не изменился или гонка номера.
     * @throws SpaceConflictException Если голова уже не expectedRevision.
     */
    public function publish(
        int $spaceId,
        PreparedComposition $preparedComposition,
        bool $allowUnchangedComposition = false,
        ?int $expectedRevision = null,
    ): WrittenRevision {
        $versionIds = $preparedComposition->getVersionIds();
        if (!$allowUnchangedComposition) {
            $this->assertCompositionChanged($spaceId, $versionIds);
        }
        if ($expectedRevision !== null) {
            $this->assertExpectedHead($spaceId, $expectedRevision);
        }
        $revisionRow = $expectedRevision === null
            ? $this->insertRevision($spaceId)
            : $this->insertExpectedRevision($spaceId, $expectedRevision);
        $this->insertItems($revisionRow['id'], $versionIds);
        $revisionRecord = RevisionRecord::fromNormalized($revisionRow);

        return new WrittenRevision(
            $revisionRecord,
            new RevisionSlice($revisionRecord, $this->sliceItems($preparedComposition, $versionIds)),
        );
    }

    /**
     * Отказывает, если множество version_id совпало с последней ревизией.
     *
     * @param int $spaceId Пространство.
     * @param array<int, int> $versionIds Новый состав.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если множества равны.
     */
    private function assertCompositionChanged(int $spaceId, array $versionIds): void
    {
        $lastRow = $this->openedCluster->getRevisions()->getFirst(ListQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId],
            'sort' => ['revision' => 'desc'],
            'limit' => 1,
        ]));
        if ($lastRow === null) {
            return;
        }

        $previousIds = [];
        foreach (
            $this->openedCluster->getItems()->getList(ListQuery::fromOptions([
                'filter' => ['=revision_id' => $lastRow['id']],
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]))->rows() as $itemRow
        ) {
            if (is_int($itemRow['version_id'] ?? null)) {
                $previousIds[] = $itemRow['version_id'];
            }
        }

        $this->assertSetsDiffer($previousIds, $versionIds);
    }

    /**
     * Сравнивает множества id.
     *
     * @param array<int, int> $previousIds Старый состав.
     * @param array<int, int> $versionIds Новый состав.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если равны.
     */
    private function assertSetsDiffer(array $previousIds, array $versionIds): void
    {
        $previousSet = $previousIds;
        $nextSet = $versionIds;
        sort($previousSet);
        sort($nextSet);
        if ($previousSet === $nextSet) {
            throw new SpaceInvalidException('Composition is unchanged');
        }
    }

    /**
     * Отказывает, если голова уже не ожидаемая.
     *
     * @param int $spaceId Пространство.
     * @param int $expectedRevision Номер базы.
     *
     * @return void
     *
     * @throws SpaceConflictException Если MAX не совпал.
     */
    private function assertExpectedHead(int $spaceId, int $expectedRevision): void
    {
        $actualRevision = $this->currentHead($spaceId);
        if ($actualRevision !== $expectedRevision) {
            throw new SpaceConflictException($expectedRevision, $actualRevision);
        }
    }

    /**
     * Insert ровно expected+1. Unique — конфликт, без перехода на следующий номер.
     *
     * @param int $spaceId Пространство.
     * @param int $expectedRevision Номер базы.
     *
     * @return array<string, mixed> Строка ревизии.
     *
     * @throws SpaceConflictException Если номер уже занят.
     */
    private function insertExpectedRevision(int $spaceId, int $expectedRevision): array
    {
        try {
            return $this->addRevisionRow($spaceId, $expectedRevision + 1);
        } catch (UniqueConstraintException $exception) {
            $actualRevision = $this->currentHead($spaceId);
            if ($actualRevision === $expectedRevision) {
                $actualRevision = $expectedRevision + 1;
            }

            throw new SpaceConflictException($expectedRevision, $actualRevision, $exception);
        }
    }

    /**
     * Текущая голова или 0.
     *
     * @param int $spaceId Пространство.
     *
     * @return int Номер.
     */
    private function currentHead(int $spaceId): int
    {
        return $this->nextRevision($spaceId) - 1;
    }

    /**
     * Insert ревизии с одним retry на unique.
     *
     * @param int $spaceId Пространство.
     *
     * @return array<string, mixed> Строка ревизии.
     *
     * @throws SpaceInvalidException Если второй unique.
     */
    private function insertRevision(int $spaceId): array
    {
        try {
            return $this->addRevisionRow($spaceId, $this->nextRevision($spaceId));
        } catch (UniqueConstraintException) {
            try {
                return $this->addRevisionRow($spaceId, $this->nextRevision($spaceId));
            } catch (UniqueConstraintException $retryException) {
                throw new SpaceInvalidException('Revision number collision', $retryException);
            }
        }
    }

    /**
     * MAX+1 или 1.
     *
     * @param int $spaceId Пространство.
     *
     * @return int Номер.
     */
    private function nextRevision(int $spaceId): int
    {
        $rows = $this->openedCluster->getRevisions()->aggregate(AggregateQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId],
            'group' => ['space_id'],
            'select' => ['space_id', new MaxField('revision', 'max_revision')],
            'limit' => 1,
        ]))->rows();
        if ($rows === []) {
            return 1;
        }

        return ((int) $rows[0]['max_revision']) + 1;
    }

    /**
     * Insert и getById ревизии.
     *
     * @param int $spaceId Пространство.
     * @param int $revisionNumber Номер.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws SpaceInvalidException Если строки нет после insert.
     */
    private function addRevisionRow(int $spaceId, int $revisionNumber): array
    {
        $revisionId = $this->openedCluster->getRevisions()->add([
            'space_id' => $spaceId,
            'revision' => $revisionNumber,
        ]);
        $revisionRow = $this->openedCluster->getRevisions()->getById($revisionId);
        if ($revisionRow === null) {
            throw new SpaceInvalidException('Revision row is missing');
        }

        return $revisionRow;
    }

    /**
     * Пункты состава.
     *
     * @param int $revisionId Id ревизии.
     * @param array<int, int> $versionIds Состав.
     *
     * @return void
     */
    private function insertItems(int $revisionId, array $versionIds): void
    {
        $itemRows = [];
        foreach ($versionIds as $versionId) {
            $itemRows[] = [
                'revision_id' => $revisionId,
                'version_id' => $versionId,
            ];
        }

        $this->openedCluster->getItems()->addMany($itemRows);
    }

    /**
     * VersionRecord в порядке состава.
     *
     * @param PreparedComposition $preparedComposition Ряды.
     * @param array<int, int> $versionIds Порядок.
     *
     * @return array<int, VersionRecord> Пункты.
     *
     * @throws SpaceInvalidException Если ряда нет.
     */
    private function sliceItems(PreparedComposition $preparedComposition, array $versionIds): array
    {
        $items = [];
        foreach ($versionIds as $versionId) {
            $versionRow = $preparedComposition->findVersionRow($versionId);
            if ($versionRow === null) {
                throw new SpaceInvalidException('Version row is missing');
            }

            $items[] = VersionRecord::fromNormalized($versionRow);
        }

        return $items;
    }
}
