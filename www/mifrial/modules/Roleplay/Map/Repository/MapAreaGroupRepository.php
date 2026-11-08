<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException as SmartMapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Map\Dto\MapAreaGroupRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_area_group`: группы однотипных областей.
 */
final class MapAreaGroupRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $groupRecords Строки групп.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $groupRecords,
    ) {
    }

    /**
     * Добавляет группу.
     *
     * @param int $mapId Карта.
     * @param string $label Подпись.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function add(int $mapId, string $label): int
    {
        try {
            return $this->groupRecords->add([
                'map_id' => $mapId,
                'label' => $label,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map area group row is invalid', $exception);
        }
    }

    /**
     * Группы карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapAreaGroupRecord> Группы.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByMap(int $mapId): array
    {
        $listResult = $this->listResult($mapId);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapAreaGroupRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет подпись группы.
     *
     * @param int $groupId Id.
     * @param string $label Подпись.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $groupId, string $label): void
    {
        try {
            $this->groupRecords->update($groupId, ['label' => $label]);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map area group was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map area group field is invalid', $exception);
        }
    }

    /**
     * Удаляет группу; области остаются без группы.
     *
     * @param int $groupId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function delete(int $groupId): void
    {
        try {
            $this->groupRecords->delete($groupId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map area group was not found', $exception);
        }
    }

    /**
     * Читает группы карты.
     *
     * @param int $mapId Карта.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function listResult(int $mapId): ListResult
    {
        try {
            return $this->groupRecords->getList(ListQuery::fromOptions([
                'filter' => ['=map_id' => $mapId],
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map area group list is invalid', $exception);
        }
    }
}
