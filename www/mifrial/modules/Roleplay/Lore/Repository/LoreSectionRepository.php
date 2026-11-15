<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Repository;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Lore\Dto\LoreSectionRecord;
use Mifrial\Roleplay\Lore\Dto\LoreSectionValues;
use Mifrial\Roleplay\Lore\Service\LoreRowErrors;

/**
 * Строки `lore_section`: дерево разделов мира.
 */
final class LoreSectionRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $sectionRecords Строки разделов.
     * @param LoreRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $sectionRecords,
        private readonly LoreRowErrors $errors,
    ) {
    }

    /**
     * Все разделы мира.
     *
     * @param int $worldId Мир.
     *
     * @return array<int, LoreSectionRecord> Разделы.
     */
    public function listByWorld(int $worldId): array
    {
        $listResult = $this->errors->list($this->sectionRecords, ListQuery::fromOptions([
            'filter' => ['=world_id' => $worldId],
            'sort' => ['sort_order' => 'asc', 'id' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore section list is invalid');
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LoreSectionRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Коды разделов мира.
     *
     * @param int $worldId Мир.
     *
     * @return array<int, string> Коды.
     */
    public function codesByWorld(int $worldId): array
    {
        $codes = [];
        foreach ($this->listByWorld($worldId) as $sectionRecord) {
            $codes[] = $sectionRecord->getCode();
        }

        return $codes;
    }

    /**
     * Заменяет дерево целиком.
     *
     * @param int $worldId Мир.
     * @param array<int, LoreSectionValues> $sections Новое дерево.
     * @param DateTime $now Момент.
     *
     * @return void
     */
    public function replaceTree(int $worldId, array $sections, DateTime $now): void
    {
        foreach ($this->listByWorld($worldId) as $existingRecord) {
            $this->errors->deleteById(
                $this->sectionRecords,
                $existingRecord->getId(),
                'Lore section is referenced and cannot be removed',
            );
        }

        $rows = [];
        foreach ($sections as $sectionValues) {
            $rows[] = [
                'world_id' => $worldId,
                'code' => $sectionValues->getCode(),
                'name' => $sectionValues->getName(),
                'description' => $sectionValues->getDescription(),
                'parent_code' => $sectionValues->getParentCode(),
                'sort_order' => $sectionValues->getSortOrder(),
                'icon' => $sectionValues->getIcon(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->errors->insertMany($this->sectionRecords, $rows, 'Lore section row is invalid');
    }
}
