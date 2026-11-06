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
use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_area`: стабильные области карты.
 */
final class MapAreaRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $areaRecords Строки областей.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $areaRecords,
    ) {
    }

    /**
     * Добавляет область.
     *
     * @param int $mapId Карта.
     * @param string $layer Слой: surface или object.
     * @param string $color Цвет.
     * @param int $speed Проходимость.
     * @param string $label Подпись.
     * @param int|null $nodeId Место.
     * @param int $sortOrder Порядок внутри слоя.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function add(
        int $mapId,
        string $layer,
        string $color,
        int $speed,
        string $label,
        ?int $nodeId,
        int $sortOrder,
    ): int {
        try {
            return $this->areaRecords->add([
                'map_id' => $mapId,
                'layer' => $layer,
                'color' => $color,
                'speed' => $speed,
                'label' => $label,
                'node_id' => $nodeId,
                'sort_order' => $sortOrder,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map area row is invalid', $exception);
        }
    }

    /**
     * Область по id.
     *
     * @param int $areaId Id.
     *
     * @return MapAreaRecord Область.
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getById(int $areaId): MapAreaRecord
    {
        $row = $this->areaRecords->getById($areaId);
        if ($row === null) {
            throw new MapNotFoundException('Map area was not found');
        }

        return MapAreaRecord::fromNormalized($row);
    }

    /**
     * Область в границах карты.
     *
     * @param int $mapId Карта.
     * @param int $areaId Область.
     *
     * @return MapAreaRecord Область.
     *
     * @throws MapNotFoundException Если области нет или она чужая.
     * @throws MapInvalidException Если строка битая.
     */
    public function getForMap(int $mapId, int $areaId): MapAreaRecord
    {
        $row = $this->areaRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['LOGIC' => 'AND', ['=id' => $areaId], ['=map_id' => $mapId]],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new MapNotFoundException('Map area was not found');
        }

        return MapAreaRecord::fromNormalized($row);
    }

    /**
     * Области карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapAreaRecord> Области.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByMap(int $mapId): array
    {
        $listResult = $this->listResult($mapId);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapAreaRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет поля области.
     *
     * @param int $areaId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $areaId, array $fields): void
    {
        try {
            $this->areaRecords->update($areaId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map area was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map area field is invalid', $exception);
        }
    }

    /**
     * Удаляет область.
     *
     * @param int $areaId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function delete(int $areaId): void
    {
        try {
            $this->areaRecords->delete($areaId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map area was not found', $exception);
        }
    }

    /**
     * Читает области карты.
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
            return $this->areaRecords->getList(ListQuery::fromOptions([
                'filter' => ['=map_id' => $mapId],
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map area list is invalid', $exception);
        }
    }
}
