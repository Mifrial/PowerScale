<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Repository;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Versioning\Space\Dto\CommitEntry;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSlice;

/**
 * Карты Rule ↔ часы.
 */
final class RuleClockMapper
{
    /**
     * Создаёт маппер.
     *
     * @param RuleIdentityRepository $ruleIdentityRepository Identity.
     *
     * @return void
     */
    public function __construct(
        private readonly RuleIdentityRepository $ruleIdentityRepository,
    ) {
    }

    /**
     * Пункты часов из входа Rule.
     *
     * @param array<int, RuleCommitEntry> $entries Состав.
     *
     * @return array<int, CommitEntry> Часы.
     *
     * @throws RuleInvalidException Если дубль code.
     */
    public function toClockEntries(array $entries): array
    {
        $this->assertUniquePutCodes($entries);
        $clockEntries = [];
        foreach ($entries as $entry) {
            $clockEntries[] = $this->toClockEntry($entry);
        }

        return $clockEntries;
    }

    /**
     * Срез Rule из часов.
     *
     * @param RevisionSlice $revisionSlice Часы.
     *
     * @return RuleRevisionSlice Правило.
     *
     * @throws RuleInvalidException Если identity нет.
     */
    public function toRuleSlice(RevisionSlice $revisionSlice): RuleRevisionSlice
    {
        $entityIds = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $entityIds[] = $versionRecord->getEntityId();
        }

        $codesById = $this->ruleIdentityRepository->getCodesByEntityIds($entityIds);
        $items = [];
        foreach ($revisionSlice->getItems() as $versionRecord) {
            $items[] = RuleVersionRecord::fromClock(
                $versionRecord,
                $codesById[$versionRecord->getEntityId()],
            );
        }

        return new RuleRevisionSlice(RuleRevisionRecord::fromClock($revisionSlice->getRevision()), $items);
    }

    /**
     * Ревизия Rule.
     *
     * @param RevisionRecord $revisionRecord Часы.
     *
     * @return RuleRevisionRecord Правило.
     */
    public function toRuleRevision(RevisionRecord $revisionRecord): RuleRevisionRecord
    {
        return RuleRevisionRecord::fromClock($revisionRecord);
    }

    /**
     * Один пункт часов.
     *
     * @param RuleCommitEntry $entry Вход.
     *
     * @return CommitEntry Часы.
     *
     * @throws RuleInvalidException Если put без тела.
     */
    private function toClockEntry(RuleCommitEntry $entry): CommitEntry
    {
        $versionId = $entry->getVersionId();
        if ($versionId !== null) {
            return CommitEntry::keep($versionId);
        }

        $code = $entry->getCode();
        $body = $entry->getBody();
        if ($code === null || !$body instanceof RuleVersionBody) {
            throw new RuleInvalidException('Rule commit entry is invalid');
        }

        $versionFields = $this->versionFields($body);
        $entityId = $this->ruleIdentityRepository->findIdByCode($code);
        if ($entityId === null) {
            return CommitEntry::create(['code' => $code], $versionFields, $entry->isActive());
        }

        return CommitEntry::change($entityId, $versionFields, $entry->isActive());
    }

    /**
     * Колонки версии.
     *
     * @param RuleVersionBody $body Снимок.
     *
     * @return array<string, mixed> Карта.
     */
    private function versionFields(RuleVersionBody $body): array
    {
        return [
            'type' => $body->getType(),
            'name' => $body->getName(),
            'description' => $body->getDescription(),
            'spec' => $body->getSpec(),
            'keywords' => $body->getKeywordIds(),
            'mechanic_id' => $body->getMechanicId(),
            'mechanic_payload' => $body->getMechanicPayload(),
            'content_status' => $body->getContentStatus(),
        ];
    }

    /**
     * Два put с одним code.
     *
     * @param array<int, RuleCommitEntry> $entries Состав.
     *
     * @return void
     *
     * @throws RuleInvalidException Если дубль.
     */
    private function assertUniquePutCodes(array $entries): void
    {
        $seenCodes = [];
        foreach ($entries as $entry) {
            $code = $entry->getCode();
            if ($code === null) {
                continue;
            }

            if (isset($seenCodes[$code])) {
                throw new RuleInvalidException('Rule code appears twice');
            }

            $seenCodes[$code] = true;
        }
    }
}
