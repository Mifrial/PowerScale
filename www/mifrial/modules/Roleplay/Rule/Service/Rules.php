<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSummary;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleNotFoundException;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Repository\RuleClockMapper;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSummary;
use Mifrial\Versioning\Space\Interface\Service\IVersionedRepository;

/**
 * Фасад правил на часах.
 */
final class Rules implements IRules
{
    /**
     * Создаёт фасад.
     *
     * @param IVersionedRepository $versionedRepository Часы кластера.
     * @param RuleClockMapper $ruleClockMapper Карты.
     *
     * @return void
     */
    public function __construct(
        private readonly IVersionedRepository $versionedRepository,
        private readonly RuleClockMapper $ruleClockMapper,
    ) {
    }

    /**
     * Создаёт пространство.
     *
     * @param string $label Подпись.
     *
     * @return int Space id.
     *
     * @throws RuleInvalidException Если пусто.
     */
    public function addSpace(string $label): int
    {
        $spaceTitle = trim($label);
        if ($spaceTitle === '') {
            throw new RuleInvalidException('Space title must not be empty');
        }

        $spaceId = (new RuleGuard())->run(
            fn (): mixed => $this->versionedRepository->addSpace($spaceTitle),
        );
        if (!is_int($spaceId)) {
            throw new RuleInvalidException('Space id is invalid');
        }

        return $spaceId;
    }

    /**
     * Пишет подпись часов.
     *
     * @param int $spaceId Пространство.
     * @param string $label Подпись.
     *
     * @return void
     *
     * @throws RuleInvalidException Если пусто.
     * @throws RuleNotFoundException Если пространства нет.
     */
    public function updateSpace(int $spaceId, string $label): void
    {
        $spaceTitle = trim($label);
        if ($spaceTitle === '') {
            throw new RuleInvalidException('Space title must not be empty');
        }

        (new RuleGuard())->run(
            function () use ($spaceId, $spaceTitle): mixed {
                $this->versionedRepository->updateSpace($spaceId, $spaceTitle);

                return null;
            },
        );
    }

    /**
     * Публикует полный состав.
     *
     * @param int $spaceId Пространство.
     * @param array<int, RuleCommitEntry> $entries Keep/put.
     * @param bool $allowUnchangedComposition Разрешить тот же набор version_id.
     *
     * @return RuleRevisionRecord Ревизия.
     *
     * @throws RuleInvalidException Если состав недопустим.
     * @throws RuleNotFoundException Если пространства нет.
     */
    public function commit(
        int $spaceId,
        array $entries,
        bool $allowUnchangedComposition = false,
    ): RuleRevisionRecord {
        $parsedEntries = $this->parseEntries($entries);
        $revisionRecord = (new RuleGuard())->run(
            function () use ($spaceId, $parsedEntries, $allowUnchangedComposition): mixed {
                $clockEntries = $this->ruleClockMapper->toClockEntries($parsedEntries);

                return $this->versionedRepository->commit(
                    $spaceId,
                    $clockEntries,
                    $allowUnchangedComposition,
                );
            },
        );
        if (!$revisionRecord instanceof RevisionRecord) {
            throw new RuleInvalidException('Revision record is invalid');
        }

        return $this->ruleClockMapper->toRuleRevision($revisionRecord);
    }

    /**
     * Срез ревизии.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     *
     * @return RuleRevisionSlice Состав.
     *
     * @throws RuleInvalidException Если номер меньше 1.
     * @throws RuleNotFoundException Если ревизии нет.
     */
    public function getRevision(int $spaceId, int $revision): RuleRevisionSlice
    {
        $revisionSlice = (new RuleGuard())->run(
            function () use ($spaceId, $revision): mixed {
                return $this->ruleClockMapper->toRuleSlice(
                    $this->versionedRepository->getRevision($spaceId, $revision),
                );
            },
        );
        if (!$revisionSlice instanceof RuleRevisionSlice) {
            throw new RuleInvalidException('Revision slice is invalid');
        }

        return $revisionSlice;
    }

    /**
     * Правило с code в составе.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     * @param string $code Ключ.
     *
     * @return RuleVersionRecord Экземпляр.
     *
     * @throws RuleInvalidException Если code пуст.
     * @throws RuleNotFoundException Если нет в составе.
     */
    public function findInRevision(int $spaceId, int $revision, string $code): RuleVersionRecord
    {
        $trimmedCode = trim($code);
        if ($trimmedCode === '') {
            throw new RuleInvalidException('Rule code must not be empty');
        }

        foreach ($this->getRevision($spaceId, $revision)->getItems() as $versionRecord) {
            if ($versionRecord->getCode() === $trimmedCode) {
                return $versionRecord;
            }
        }

        throw new RuleNotFoundException();
    }

    /**
     * Последняя ревизия часов или null.
     *
     * @param int $spaceId Пространство.
     *
     * @return RuleRevisionRecord|null Ревизия.
     *
     * @throws RuleNotFoundException Если пространства нет.
     * @throws RuleInvalidException Если запись часов битая.
     */
    public function findLatestRevision(int $spaceId): ?RuleRevisionRecord
    {
        $revisionRecord = (new RuleGuard())->run(
            fn (): mixed => $this->versionedRepository->findLatestRevision($spaceId),
        );
        if ($revisionRecord === null) {
            return null;
        }

        if (!$revisionRecord instanceof RevisionRecord) {
            throw new RuleInvalidException('Revision record is invalid');
        }

        return $this->ruleClockMapper->toRuleRevision($revisionRecord);
    }

    /**
     * Лента ревизий без пунктов состава.
     *
     * @param int $spaceId Пространство.
     *
     * @return array<int, RuleRevisionSummary> Сводки.
     *
     * @throws RuleNotFoundException Если пространства нет.
     * @throws RuleInvalidException Если запись часов битая.
     */
    public function getRevisionList(int $spaceId): array
    {
        $summaries = (new RuleGuard())->run(
            fn (): mixed => $this->versionedRepository->getRevisionList($spaceId),
        );
        if (!is_array($summaries)) {
            throw new RuleInvalidException('Revision list is invalid');
        }

        $ruleSummaries = [];
        foreach ($summaries as $revisionSummary) {
            if (!$revisionSummary instanceof RevisionSummary) {
                throw new RuleInvalidException('Revision list is invalid');
            }

            $ruleSummaries[] = RuleRevisionSummary::fromClock($revisionSummary);
        }

        return $ruleSummaries;
    }

    /**
     * Проверяет list пунктов.
     *
     * @param array<int|string, mixed> $entries Вход.
     *
     * @return array<int, RuleCommitEntry> Пункты.
     *
     * @throws RuleInvalidException Если форма неверна.
     */
    private function parseEntries(array $entries): array
    {
        if (!array_is_list($entries) || $entries === [] || count($entries) > 10000) {
            throw new RuleInvalidException('Commit entries are invalid');
        }

        foreach ($entries as $entry) {
            if (!$entry instanceof RuleCommitEntry) {
                throw new RuleInvalidException('Commit entries are invalid');
            }
        }

        return $entries;
    }
}
