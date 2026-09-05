<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceRepository;

/**
 * Insert мира и публикация с указателем каталога.
 */
final class RuleSpaceWorldWriter
{
    /**
     * Создаёт писатель.
     *
     * @param IRules $rules Часы.
     * @param RuleSpaceRepository $ruleSpaceRepository Sidecar.
     * @param RuleSpaceCatalogBinder $catalogBinder Каталог.
     *
     * @return void
     */
    public function __construct(
        private readonly IRules $rules,
        private readonly RuleSpaceRepository $ruleSpaceRepository,
        private readonly RuleSpaceCatalogBinder $catalogBinder,
    ) {
    }

    /**
     * Часы, sidecar и inherit состава.
     *
     * @param string $code Ключ.
     * @param string $name Подпись.
     * @param int $ownerUserId Владелец.
     * @param string $description Текст.
     * @param RuleRevisionSlice|null $sourceSlice Состав родителя.
     * @param int|null $inheritFromSpaceId Родитель.
     *
     * @return int Space id.
     */
    public function insertWorld(
        string $code,
        string $name,
        int $ownerUserId,
        string $description,
        ?RuleRevisionSlice $sourceSlice,
        ?int $inheritFromSpaceId,
    ): int {
        $spaceId = $this->rules->addSpace($name);
        $this->ruleSpaceRepository->add($spaceId, $code, $name, $ownerUserId, $description);
        if ($sourceSlice instanceof RuleRevisionSlice && $inheritFromSpaceId !== null) {
            $revisionRecord = $this->rules->commit($spaceId, $this->keepEntries($sourceSlice));
            $this->catalogBinder->copyInherited(
                $inheritFromSpaceId,
                $sourceSlice->getRevision()->getRevision(),
                $spaceId,
                $revisionRecord->getRevision(),
            );
        }

        return $spaceId;
    }

    /**
     * Часы и указатель каталога.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $entries Keep/put.
     * @param RuleSpaceCatalog|null $catalog Снимок.
     *
     * @return RuleRevisionRecord Ревизия.
     */
    public function publishWithCatalog(
        int $spaceId,
        array $entries,
        ?RuleSpaceCatalog $catalog,
    ): RuleRevisionRecord {
        $revisionRecord = $this->rules->commit(
            $spaceId,
            $entries,
            $this->allowUnchangedComposition($spaceId, $entries, $catalog),
        );
        $this->catalogBinder->bindAfterCommit(
            $spaceId,
            $revisionRecord->getRevision(),
            $catalog,
            $this->compositionCodes($spaceId, $revisionRecord->getRevision()),
        );

        return $revisionRecord;
    }

    /**
     * Снимок каталога ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return RuleSpaceCatalog Снимок.
     */
    public function getCatalog(int $spaceId, int $revision): RuleSpaceCatalog
    {
        return $this->catalogBinder->getCatalog($spaceId, $revision);
    }

    /**
     * Каталог-only: все keep и дерево не как latest.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $entries Пункты.
     * @param RuleSpaceCatalog|null $catalog Снимок.
     *
     * @return bool true, если часы принимают тот же состав.
     */
    private function allowUnchangedComposition(
        int $spaceId,
        array $entries,
        ?RuleSpaceCatalog $catalog,
    ): bool {
        if (!$catalog instanceof RuleSpaceCatalog || !$this->areAllKeep($entries)) {
            return false;
        }

        $latestCatalog = $this->catalogBinder->findLatestCatalog($spaceId);
        if ($latestCatalog === null) {
            return false;
        }

        return $latestCatalog->fingerprint() !== $catalog->fingerprint();
    }

    /**
     * Все пункты — keep.
     *
     * @param array<int, RuleCommitEntry> $entries Пункты.
     *
     * @return bool true, если нет put.
     */
    private function areAllKeep(array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($entry->getVersionId() === null) {
                return false;
            }
        }

        return $entries !== [];
    }

    /**
     * Коды состава ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return array<string, true> Коды.
     */
    private function compositionCodes(int $spaceId, int $revision): array
    {
        $ruleCodes = [];
        foreach ($this->rules->getRevision($spaceId, $revision)->getItems() as $versionRecord) {
            $ruleCodes[$versionRecord->getCode()] = true;
        }

        return $ruleCodes;
    }

    /**
     * Keep всех пунктов среза.
     *
     * @param RuleRevisionSlice $sourceSlice Родитель.
     *
     * @return array<int, RuleCommitEntry> Пункты.
     */
    private function keepEntries(RuleRevisionSlice $sourceSlice): array
    {
        $entries = [];
        foreach ($sourceSlice->getItems() as $versionRecord) {
            $entries[] = RuleCommitEntry::keep($versionRecord->getVersionId());
        }

        return $entries;
    }
}
