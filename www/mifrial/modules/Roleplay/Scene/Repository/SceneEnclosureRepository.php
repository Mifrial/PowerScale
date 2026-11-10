<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Scene\Dto\SceneEnclosureRecord;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneNotFoundException;
use Mifrial\Roleplay\Scene\Service\SceneRowErrors;

/**
 * Строки `scene_enclosure`: вложения полей.
 */
final class SceneEnclosureRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $enclosureRecords Строки вложений.
     * @param SceneRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $enclosureRecords,
        private readonly SceneRowErrors $errors,
    ) {
    }

    /**
     * Вложения родительских полей.
     *
     * @param array<int, int> $boardIds Id полей.
     *
     * @return array<int, SceneEnclosureRecord> Вложения.
     *
     * @throws SceneInvalidException Если выборка недопустима.
     */
    public function findByParentBoardIds(array $boardIds): array
    {
        if ($boardIds === []) {
            return [];
        }

        $listResult = $this->errors->list(
            $this->enclosureRecords,
            ListQuery::fromOptions([
                'filter' => ['=parent_board_id' => array_values($boardIds)],
                'sort' => ['sort_order' => 'asc', 'id' => 'asc'],
                'limit' => 10000,
                'offset' => 0,
                'countTotal' => false,
            ]),
            'Scene enclosure query is invalid',
        );
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = SceneEnclosureRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Вставляет вложение.
     *
     * @param int $parentBoardId Родительское поле.
     * @param int $childBoardId Вложенное поле.
     * @param array<string|int, mixed> $footprint Контур.
     * @param int $sortOrder Порядок.
     *
     * @return int Id.
     *
     * @throws SceneInvalidException Если строка недопустима.
     */
    public function add(
        int $parentBoardId,
        int $childBoardId,
        array $footprint,
        int $sortOrder,
    ): int {
        return $this->errors->insert(
            $this->enclosureRecords,
            [
                'parent_board_id' => $parentBoardId,
                'child_board_id' => $childBoardId,
                'footprint' => $footprint,
                'sort_order' => $sortOrder,
            ],
            'Scene enclosure is invalid',
        );
    }

    /**
     * Пишет вложение.
     *
     * @param int $enclosureId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если поле недопустимо.
     */
    public function update(int $enclosureId, array $fields): void
    {
        $this->errors->updateById($this->enclosureRecords, $enclosureId, $fields, 'Scene enclosure field is invalid');
    }

    /**
     * Удаляет вложение.
     *
     * @param int $enclosureId Id.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если удаление запрещено.
     */
    public function delete(int $enclosureId): void
    {
        $this->errors->deleteById($this->enclosureRecords, $enclosureId, 'Scene enclosure is referenced');
    }
}
