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
use Mifrial\Roleplay\Map\Dto\MapNodeRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_node`: точки интереса и waypoints.
 */
final class MapNodeRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $nodeRecords Строки узлов.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $nodeRecords,
    ) {
    }

    /**
     * Добавляет узел.
     *
     * @param int $mapId Карта.
     * @param string $name Имя.
     * @param string $shortInfo Короткая информация.
     * @param string $publicInfo Раскрываемая информация.
     * @param string $gmInfo Информация ведущего.
     * @param int|null $linkedMapId Связанная карта.
     * @param string $icon Иконка.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function add(
        int $mapId,
        string $name,
        string $shortInfo,
        string $publicInfo,
        string $gmInfo,
        ?int $linkedMapId,
        string $icon,
    ): int {
        try {
            return $this->nodeRecords->add([
                'map_id' => $mapId,
                'name' => $name,
                'short_info' => $shortInfo,
                'public_info' => $publicInfo,
                'gm_info' => $gmInfo,
                'linked_map_id' => $linkedMapId,
                'icon' => $icon,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map node row is invalid', $exception);
        }
    }

    /**
     * Узел по id.
     *
     * @param int $nodeId Id.
     *
     * @return MapNodeRecord Узел.
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getById(int $nodeId): MapNodeRecord
    {
        $row = $this->nodeRecords->getById($nodeId);
        if ($row === null) {
            throw new MapNotFoundException('Map node was not found');
        }

        return MapNodeRecord::fromNormalized($row);
    }

    /**
     * Узел в границах карты.
     *
     * @param int $mapId Карта.
     * @param int $nodeId Узел.
     *
     * @return MapNodeRecord Узел.
     *
     * @throws MapNotFoundException Если узла нет или он чужой.
     * @throws MapInvalidException Если строка битая.
     */
    public function getForMap(int $mapId, int $nodeId): MapNodeRecord
    {
        $row = $this->nodeRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['LOGIC' => 'AND', ['=id' => $nodeId], ['=map_id' => $mapId]],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new MapNotFoundException('Map node was not found');
        }

        return MapNodeRecord::fromNormalized($row);
    }

    /**
     * Узлы карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapNodeRecord> Узлы.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByMap(int $mapId): array
    {
        $listResult = $this->listResult($mapId);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapNodeRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет поля узла.
     *
     * @param int $nodeId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $nodeId, array $fields): void
    {
        try {
            $this->nodeRecords->update($nodeId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map node was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map node field is invalid', $exception);
        }
    }

    /**
     * Удаляет узел.
     *
     * @param int $nodeId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function delete(int $nodeId): void
    {
        try {
            $this->nodeRecords->delete($nodeId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map node was not found', $exception);
        }
    }

    /**
     * Читает узлы карты.
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
            return $this->nodeRecords->getList(ListQuery::fromOptions([
                'filter' => ['=map_id' => $mapId],
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map node list is invalid', $exception);
        }
    }
}
