<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Scene\Dto\SceneBoardRecord;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneNotFoundException;
use Mifrial\Roleplay\Scene\Service\SceneRowErrors;

/**
 * Строки `scene_board`: поля локации.
 */
final class SceneBoardRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $boardRecords Строки полей.
     * @param SceneRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $boardRecords,
        private readonly SceneRowErrors $errors,
    ) {
    }

    /**
     * Поля локации в порядке отображения.
     *
     * @param int $locationId Локация.
     *
     * @return array<int, SceneBoardRecord> Поля.
     *
     * @throws SceneInvalidException Если выборка недопустима.
     */
    public function findByLocation(int $locationId): array
    {
        $listResult = $this->errors->list(
            $this->boardRecords,
            ListQuery::fromOptions([
                'filter' => ['=location_id' => $locationId],
                'sort' => ['sort_order' => 'asc', 'id' => 'asc'],
                'limit' => 10000,
                'offset' => 0,
                'countTotal' => false,
            ]),
            'Scene board query is invalid',
        );
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = SceneBoardRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Вставляет поле.
     *
     * @param int $locationId Локация.
     * @param string $name Имя.
     * @param int $sortOrder Порядок.
     * @param int|null $baseFileId Подложка.
     * @param int|null $baseFileWidth Ширина подложки.
     * @param int|null $baseFileHeight Высота подложки.
     * @param int $boundWidth Логическая ширина.
     * @param int $boundHeight Логическая высота.
     * @param int $occupancyCell Клетка occupancy.
     * @param int $zQuantum Квант высоты.
     * @param int $gridStep Шаг сетки.
     *
     * @return int Id.
     *
     * @throws SceneInvalidException Если строка недопустима.
     */
    public function add(
        int $locationId,
        string $name,
        int $sortOrder,
        ?int $baseFileId,
        ?int $baseFileWidth,
        ?int $baseFileHeight,
        int $boundWidth,
        int $boundHeight,
        int $occupancyCell,
        int $zQuantum,
        int $gridStep,
    ): int {
        return $this->errors->insert(
            $this->boardRecords,
            [
                'location_id' => $locationId,
                'name' => $name,
                'sort_order' => $sortOrder,
                'base_file_id' => $baseFileId,
                'base_file_width' => $baseFileWidth,
                'base_file_height' => $baseFileHeight,
                'bound_width' => $boundWidth,
                'bound_height' => $boundHeight,
                'occupancy_cell' => $occupancyCell,
                'z_quantum' => $zQuantum,
                'grid_step' => $gridStep,
            ],
            'Scene board is invalid',
        );
    }

    /**
     * Пишет поля поля.
     *
     * @param int $boardId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если поле недопустимо.
     */
    public function update(int $boardId, array $fields): void
    {
        $this->errors->updateById($this->boardRecords, $boardId, $fields, 'Scene board field is invalid');
    }

    /**
     * Удаляет поле.
     *
     * @param int $boardId Id.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если удаление запрещено.
     */
    public function delete(int $boardId): void
    {
        $this->errors->deleteById($this->boardRecords, $boardId, 'Scene board is referenced');
    }
}
