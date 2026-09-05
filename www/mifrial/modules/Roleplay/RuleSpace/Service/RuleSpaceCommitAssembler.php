<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;

/**
 * Полный list commit из выбора и среза базы.
 */
final class RuleSpaceCommitAssembler
{
    /**
     * Собирает keep/put/tombstone.
     *
     * @param RuleRevisionSlice $baseSlice База.
     * @param RuleSpaceSelection $selection Выбор.
     *
     * @return array<int, RuleCommitEntry> Полный состав.
     *
     * @throws RuleSpaceInvalidException Если removed нет в базе или размер.
     */
    public function assemble(RuleRevisionSlice $baseSlice, RuleSpaceSelection $selection): array
    {
        $itemsByCode = $this->itemsByCode($baseSlice);
        $this->assertRemovedExist($selection, $itemsByCode);
        $putsByCode = $this->putsByCode($selection);
        $entries = $this->entriesInBaseOrder($baseSlice, $putsByCode, $selection);
        foreach ($selection->getPuts() as $putEntry) {
            $putCode = $putEntry->getCode();
            if ($putCode !== null && !isset($itemsByCode[$putCode])) {
                $entries[] = $putEntry;
            }
        }

        $this->assertAssembledSize($entries);

        return $entries;
    }

    /**
     * Пункты базы по code.
     *
     * @param RuleRevisionSlice $baseSlice База.
     *
     * @return array<string, RuleVersionRecord> Ключ = code.
     *
     * @throws RuleSpaceInvalidException Если дубль code в срезе.
     */
    private function itemsByCode(RuleRevisionSlice $baseSlice): array
    {
        $itemsByCode = [];
        foreach ($baseSlice->getItems() as $versionRecord) {
            $itemCode = $versionRecord->getCode();
            if (isset($itemsByCode[$itemCode])) {
                throw new RuleSpaceInvalidException('Revision slice codes are invalid');
            }

            $itemsByCode[$itemCode] = $versionRecord;
        }

        return $itemsByCode;
    }

    /**
     * removed должен быть в базе.
     *
     * @param RuleSpaceSelection $selection Выбор.
     * @param array<string, RuleVersionRecord> $itemsByCode База.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если code нет.
     */
    private function assertRemovedExist(RuleSpaceSelection $selection, array $itemsByCode): void
    {
        foreach ($selection->getRemovedCodes() as $removedCode) {
            if (!isset($itemsByCode[$removedCode])) {
                throw new RuleSpaceInvalidException('Removed code is not in base revision');
            }
        }
    }

    /**
     * Put по code.
     *
     * @param RuleSpaceSelection $selection Выбор.
     *
     * @return array<string, RuleCommitEntry> Ключ = code.
     */
    private function putsByCode(RuleSpaceSelection $selection): array
    {
        $putsByCode = [];
        foreach ($selection->getPuts() as $putEntry) {
            $putCode = $putEntry->getCode();
            if ($putCode !== null) {
                $putsByCode[$putCode] = $putEntry;
            }
        }

        return $putsByCode;
    }

    /**
     * Порядок базы: put / tombstone / keep.
     *
     * @param RuleRevisionSlice $baseSlice База.
     * @param array<string, RuleCommitEntry> $putsByCode Put.
     * @param RuleSpaceSelection $selection Выбор.
     *
     * @return array<int, RuleCommitEntry> Начало состава.
     *
     * @throws RuleSpaceInvalidException Если тело tombstone.
     */
    private function entriesInBaseOrder(
        RuleRevisionSlice $baseSlice,
        array $putsByCode,
        RuleSpaceSelection $selection,
    ): array {
        $removedCodes = [];
        foreach ($selection->getRemovedCodes() as $removedCode) {
            $removedCodes[$removedCode] = true;
        }

        $entries = [];
        foreach ($baseSlice->getItems() as $versionRecord) {
            $itemCode = $versionRecord->getCode();
            if (isset($removedCodes[$itemCode])) {
                $entries[] = $this->tombstoneEntry($versionRecord);
                continue;
            }

            if (isset($putsByCode[$itemCode])) {
                $entries[] = $putsByCode[$itemCode];
                continue;
            }

            $entries[] = $this->keepEntry($versionRecord);
        }

        return $entries;
    }

    /**
     * Keep пункта базы.
     *
     * @param RuleVersionRecord $versionRecord База.
     *
     * @return RuleCommitEntry Keep.
     *
     * @throws RuleSpaceInvalidException Если id недопустим.
     */
    private function keepEntry(RuleVersionRecord $versionRecord): RuleCommitEntry
    {
        try {
            return RuleCommitEntry::keep($versionRecord->getVersionId());
        } catch (RuleInvalidException $exception) {
            throw new RuleSpaceInvalidException('Keep version id is invalid', $exception);
        }
    }

    /**
     * Tombstone телом базы.
     *
     * @param RuleVersionRecord $versionRecord База.
     *
     * @return RuleCommitEntry Put inactive.
     *
     * @throws RuleSpaceInvalidException Если тело недопустимо.
     */
    private function tombstoneEntry(RuleVersionRecord $versionRecord): RuleCommitEntry
    {
        try {
            return RuleCommitEntry::put(
                $versionRecord->getCode(),
                new RuleVersionBody(
                    $versionRecord->getType(),
                    $versionRecord->getName(),
                    $versionRecord->getDescription(),
                    $versionRecord->getSpec(),
                    $versionRecord->getKeywordIds(),
                    $versionRecord->getMechanicId(),
                    $versionRecord->getMechanicPayload(),
                    $versionRecord->getContentStatus(),
                ),
                false,
            );
        } catch (RuleInvalidException $exception) {
            throw new RuleSpaceInvalidException('Tombstone body is invalid', $exception);
        }
    }

    /**
     * Пустой list и потолок часов.
     *
     * @param array<int, RuleCommitEntry> $entries Состав.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если пусто или длиннее 10000.
     */
    private function assertAssembledSize(array $entries): void
    {
        if ($entries === [] || count($entries) > 10000) {
            throw new RuleSpaceInvalidException('Assembled commit is invalid');
        }
    }
}
