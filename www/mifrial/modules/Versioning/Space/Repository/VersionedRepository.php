<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Repository;

use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Versioning\Space\Dto\CommitEntry;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSlice;
use Mifrial\Versioning\Space\Dto\RevisionSummary;
use Mifrial\Versioning\Space\Dto\SpaceRecord;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use Mifrial\Versioning\Space\Exception\SpaceNotFoundException;
use Mifrial\Versioning\Space\Interface\Service\IVersionedRepository;
use Mifrial\Versioning\Space\Service\RevisionSliceStore;
use Mifrial\Versioning\Space\Service\SpaceGuard;

/**
 * Фасад часов одного кластера: пространства, commit, срез.
 */
final class VersionedRepository implements IVersionedRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Транзакции.
     * @param OpenedCluster $openedCluster Карты.
     * @param CommitComposition $commitComposition Состав.
     * @param RevisionPublisher $revisionPublisher Часы.
     * @param RevisionLoader $revisionLoader Чтение среза.
     * @param RevisionSliceStore $revisionSliceStore Кэш.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly OpenedCluster $openedCluster,
        private readonly CommitComposition $commitComposition,
        private readonly RevisionPublisher $revisionPublisher,
        private readonly RevisionLoader $revisionLoader,
        private readonly RevisionSliceStore $revisionSliceStore,
    ) {
    }

    /**
     * Создаёт пространство репозитория.
     *
     * @param string $title Подпись.
     *
     * @return int Идентификатор пространства.
     *
     * @throws SpaceInvalidException Если title пуст после trim.
     */
    public function addSpace(string $title): int
    {
        $spaceTitle = trim($title);
        if ($spaceTitle === '') {
            throw new SpaceInvalidException('Space title is empty');
        }

        $spaceGuard = new SpaceGuard();

        return $spaceGuard->run(
            fn (): int => $this->openedCluster->getSpaces()->add(['title' => $spaceTitle]),
        );
    }

    /**
     * Читает пространство.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return SpaceRecord Пространство.
     *
     * @throws SpaceNotFoundException Если строки нет.
     */
    public function getSpace(int $spaceId): SpaceRecord
    {
        $spaceGuard = new SpaceGuard();
        $spaceRow = $spaceGuard->run(
            fn (): mixed => $this->openedCluster->getSpaces()->getById($spaceId),
        );
        if (!is_array($spaceRow)) {
            throw new SpaceNotFoundException('Space was not found');
        }

        return SpaceRecord::fromNormalized($spaceRow);
    }

    /**
     * Пишет подпись пространства.
     *
     * @param int $spaceId Идентификатор.
     * @param string $title Подпись.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если title пуст после trim.
     * @throws SpaceNotFoundException Если строки нет.
     */
    public function updateSpace(int $spaceId, string $title): void
    {
        $spaceTitle = trim($title);
        if ($spaceTitle === '') {
            throw new SpaceInvalidException('Space title is empty');
        }

        $spaceGuard = new SpaceGuard();
        $spaceGuard->run(function () use ($spaceId, $spaceTitle): mixed {
            $this->assertSpaceExists($spaceId);
            $this->openedCluster->getSpaces()->update($spaceId, ['title' => $spaceTitle]);

            return null;
        });
    }

    /**
     * Публикует новый состав.
     *
     * @param int $spaceId Пространство.
     * @param array<int, CommitEntry> $entries List keep/create/change.
     * @param bool $allowUnchangedComposition Разрешить тот же набор version_id.
     *
     * @return RevisionRecord Новая ревизия.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     * @throws SpaceInvalidException Если вход или состав недопустимы.
     */
    public function commit(
        int $spaceId,
        array $entries,
        bool $allowUnchangedComposition = false,
    ): RevisionRecord {
        $spaceGuard = new SpaceGuard();

        return $spaceGuard->run(function () use ($spaceId, $entries, $allowUnchangedComposition): RevisionRecord {
            $this->assertSpaceExists($spaceId);
            $parsedEntries = $this->commitComposition->parseEntries($entries);
            $writtenRevision = $this->smartTableGateway->transaction(
                function () use ($spaceId, $parsedEntries, $allowUnchangedComposition) {
                    $preparedComposition = $this->commitComposition->assemble($parsedEntries);

                    return $this->revisionPublisher->publish(
                        $spaceId,
                        $preparedComposition,
                        $allowUnchangedComposition,
                    );
                },
            );
            $this->revisionSliceStore->save($writtenRevision->getRevisionSlice());

            return $writtenRevision->getRevisionRecord();
        });
    }

    /**
     * Возвращает опубликованный срез.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер ревизии.
     *
     * @return RevisionSlice Состав.
     *
     * @throws SpaceInvalidException Если номер меньше 1.
     * @throws SpaceNotFoundException Если ревизии нет.
     */
    public function getRevision(int $spaceId, int $revision): RevisionSlice
    {
        if ($revision < 1) {
            throw new SpaceInvalidException('Revision number is invalid');
        }

        $spaceGuard = new SpaceGuard();

        return $spaceGuard->run(function () use ($spaceId, $revision): RevisionSlice {
            $cachedSlice = $this->revisionSliceStore->find($spaceId, $revision);
            if ($cachedSlice instanceof RevisionSlice) {
                return $cachedSlice;
            }

            $revisionSlice = $this->revisionLoader->load($spaceId, $revision);
            $this->revisionSliceStore->save($revisionSlice);

            return $revisionSlice;
        });
    }

    /**
     * Последняя ревизия пространства или null.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return RevisionRecord|null Ревизия.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     */
    public function findLatestRevision(int $spaceId): ?RevisionRecord
    {
        $spaceGuard = new SpaceGuard();

        return $spaceGuard->run(function () use ($spaceId): ?RevisionRecord {
            $this->assertSpaceExists($spaceId);
            $lastRow = $this->openedCluster->getRevisions()->getFirst(ListQuery::fromOptions([
                'filter' => ['=space_id' => $spaceId],
                'sort' => ['revision' => 'desc'],
                'limit' => 1,
            ]));
            if ($lastRow === null) {
                return null;
            }

            return RevisionRecord::fromNormalized($lastRow);
        });
    }

    /**
     * Лента ревизий: новые сверху, без пунктов состава.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return array<int, RevisionSummary> Сводки.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     * @throws SpaceInvalidException Если состав ревизии пуст.
     */
    public function getRevisionList(int $spaceId): array
    {
        $spaceGuard = new SpaceGuard();
        $summaries = $spaceGuard->run(
            fn (): mixed => $this->revisionSummaries($spaceId),
        );
        if (!is_array($summaries)) {
            throw new SpaceInvalidException('Revision list is invalid');
        }

        return $summaries;
    }

    /**
     * Пространство должно существовать.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return void
     *
     * @throws SpaceNotFoundException Если строки нет.
     */
    private function assertSpaceExists(int $spaceId): void
    {
        if ($this->openedCluster->getSpaces()->getById($spaceId) === null) {
            throw new SpaceNotFoundException('Space was not found');
        }
    }

    /**
     * Строки ревизий и counts состава.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return array<int, RevisionSummary> Сводки DESC.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     * @throws SpaceInvalidException Если count нет.
     */
    private function revisionSummaries(int $spaceId): array
    {
        $this->assertSpaceExists($spaceId);
        $revisionRows = $this->openedCluster->getRevisions()->getList(ListQuery::fromOptions([
            'filter' => ['=space_id' => $spaceId],
            'sort' => ['revision' => 'desc'],
            'limit' => ListQuery::MAX_LIMIT,
        ]))->rows();
        if ($revisionRows === []) {
            return [];
        }

        return $this->summariesFromRows(
            $revisionRows,
            $this->itemCountsByRevisionIds($this->revisionRowIds($revisionRows)),
        );
    }

    /**
     * Id строк ревизий.
     *
     * @param array<int, array<string, mixed>> $revisionRows Ряды.
     *
     * @return array<int, int> Id.
     *
     * @throws SpaceInvalidException Если id нет.
     */
    private function revisionRowIds(array $revisionRows): array
    {
        $revisionIds = [];
        foreach ($revisionRows as $revisionRow) {
            $revisionId = $revisionRow['id'] ?? null;
            if (!is_int($revisionId)) {
                throw new SpaceInvalidException('Revision record is incomplete');
            }

            $revisionIds[] = $revisionId;
        }

        return $revisionIds;
    }

    /**
     * COUNT пунктов по revision_id.
     *
     * @param array<int, int> $revisionIds Id ревизий.
     *
     * @return array<int, int> Ключ = revision_id.
     *
     * @throws SpaceInvalidException Если ряд агрегата битый.
     */
    private function itemCountsByRevisionIds(array $revisionIds): array
    {
        $countsById = [];
        foreach (array_chunk($revisionIds, 500) as $revisionIdChunk) {
            foreach ($this->itemCountRows($revisionIdChunk) as $aggregateRow) {
                $revisionId = $aggregateRow['revision_id'] ?? null;
                $itemCount = $aggregateRow['item_count'] ?? null;
                if (!is_int($revisionId) || !is_int($itemCount)) {
                    throw new SpaceInvalidException('Revision item count is invalid');
                }

                $countsById[$revisionId] = $itemCount;
            }
        }

        return $countsById;
    }

    /**
     * Ряды aggregate одной пачки.
     *
     * @param array<int, int> $revisionIdChunk До 500 id.
     *
     * @return array<int, array<string, mixed>> Группы.
     */
    private function itemCountRows(array $revisionIdChunk): array
    {
        return $this->openedCluster->getItems()->aggregate(AggregateQuery::fromOptions([
            'filter' => ['revision_id' => $revisionIdChunk],
            'group' => ['revision_id'],
            'select' => ['revision_id', new CountField('item_count')],
            'limit' => 500,
        ]))->rows();
    }

    /**
     * Сводки в порядке ленты.
     *
     * @param array<int, array<string, mixed>> $revisionRows Ряды DESC.
     * @param array<int, int> $countsById Counts.
     *
     * @return array<int, RevisionSummary> Сводки.
     *
     * @throws SpaceInvalidException Если count нет или &lt; 1.
     */
    private function summariesFromRows(array $revisionRows, array $countsById): array
    {
        $summaries = [];
        foreach ($revisionRows as $revisionRow) {
            $revisionRecord = RevisionRecord::fromNormalized($revisionRow);
            $itemCount = $countsById[$revisionRecord->getId()] ?? 0;
            if ($itemCount < 1) {
                throw new SpaceInvalidException('Revision composition is empty');
            }

            $summaries[] = new RevisionSummary(
                $revisionRecord->getId(),
                $revisionRecord->getSpaceId(),
                $revisionRecord->getRevision(),
                $revisionRecord->getPublishedAt(),
                $itemCount,
            );
        }

        return $summaries;
    }
}
