<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Scene\Dto\SceneElementRecord;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneNotFoundException;
use Mifrial\Roleplay\Scene\Service\SceneRowErrors;

/**
 * Строки `scene_element`: геометрия и декор поля.
 */
final class SceneElementRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $elementRecords Строки элементов.
     * @param SceneRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $elementRecords,
        private readonly SceneRowErrors $errors,
    ) {
    }

    /**
     * Элементы набора полей.
     *
     * @param array<int, int> $boardIds Id полей.
     *
     * @return array<int, SceneElementRecord> Элементы.
     *
     * @throws SceneInvalidException Если выборка недопустима.
     */
    public function findByBoardIds(array $boardIds): array
    {
        if ($boardIds === []) {
            return [];
        }

        $listResult = $this->errors->list(
            $this->elementRecords,
            ListQuery::fromOptions([
                'filter' => ['=board_id' => array_values($boardIds)],
                'sort' => ['sort_order' => 'asc', 'id' => 'asc'],
                'limit' => 10000,
                'offset' => 0,
                'countTotal' => false,
            ]),
            'Scene element query is invalid',
        );
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = SceneElementRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Вставляет элемент.
     *
     * @param int $boardId Поле.
     * @param string $kind Тип.
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array<string|int, mixed> $props Свойства.
     * @param int $sortOrder Порядок.
     * @param int|null $targetBoardId Целевое поле.
     * @param int|null $assetFileId Ассет.
     *
     * @return int Id.
     *
     * @throws SceneInvalidException Если строка недопустима.
     */
    public function add(
        int $boardId,
        string $kind,
        array $shape,
        array $props,
        int $sortOrder,
        ?int $targetBoardId,
        ?int $assetFileId,
    ): int {
        return $this->errors->insert(
            $this->elementRecords,
            [
                'board_id' => $boardId,
                'kind' => $kind,
                'shape' => $shape,
                'props' => $props,
                'sort_order' => $sortOrder,
                'target_board_id' => $targetBoardId,
                'asset_file_id' => $assetFileId,
            ],
            'Scene element is invalid',
        );
    }

    /**
     * Пишет элемент.
     *
     * @param int $elementId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если поле недопустимо.
     */
    public function update(int $elementId, array $fields): void
    {
        $this->errors->updateById($this->elementRecords, $elementId, $fields, 'Scene element field is invalid');
    }

    /**
     * Удаляет элемент.
     *
     * @param int $elementId Id.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если удаление запрещено.
     */
    public function delete(int $elementId): void
    {
        $this->errors->deleteById($this->elementRecords, $elementId, 'Scene element is referenced');
    }
}
