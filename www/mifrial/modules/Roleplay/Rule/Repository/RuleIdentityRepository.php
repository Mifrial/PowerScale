<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Строки identity `rule`.
 */
final class RuleIdentityRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $ruleRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $ruleRecords,
    ) {
    }

    /**
     * Id по code или null.
     *
     * @param string $code Уже trim.
     *
     * @return int|null Identity.
     */
    public function findIdByCode(string $code): ?int
    {
        $row = $this->ruleRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['code' => $code],
            'limit' => 1,
            'select' => ['id'],
        ]));
        if ($row === null || !is_int($row['id'] ?? null)) {
            return null;
        }

        return $row['id'];
    }

    /**
     * Code по entity id.
     *
     * @param array<int, int> $entityIds Identity.
     *
     * @return array<int, string> entityId → code.
     *
     * @throws RuleInvalidException Если набора нет.
     */
    public function getCodesByEntityIds(array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        $uniqueIds = array_values(array_unique($entityIds));
        $codesById = [];
        foreach (
            $this->ruleRecords->getList(ListQuery::fromOptions([
                'filter' => ['id' => $uniqueIds],
                'limit' => ListQuery::MAX_LIMIT,
                'select' => ['id', 'code'],
            ]))->rows() as $row
        ) {
            if (is_int($row['id'] ?? null) && is_string($row['code'] ?? null)) {
                $codesById[$row['id']] = $row['code'];
            }
        }

        if (count($codesById) !== count($uniqueIds)) {
            throw new RuleInvalidException('Rule identity is missing');
        }

        return $codesById;
    }
}
