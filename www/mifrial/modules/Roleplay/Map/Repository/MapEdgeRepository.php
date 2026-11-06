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
use Mifrial\Roleplay\Map\Dto\MapEdgeRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_variant_edge`: рёбра маршрута.
 */
final class MapEdgeRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $edgeRecords Строки рёбер.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $edgeRecords,
    ) {
    }

    /**
     * Добавляет ребро.
     *
     * @param int $variantId Вариант.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     * @param int $terrainId Террейн.
     * @param array<int, array{0: int, 1: int}> $path Полилиния; пусто — прямая.
     * @param int|null $distanceMeters Явное расстояние.
     * @param bool $isDirected Направленное.
     * @param string $label Подпись.
     *
     * @return int Id.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function add(
        int $variantId,
        int $fromNodeId,
        int $toNodeId,
        int $terrainId,
        array $path,
        ?int $distanceMeters,
        bool $isDirected,
        string $label,
    ): int {
        try {
            return $this->edgeRecords->add([
                'variant_id' => $variantId,
                'from_node_id' => $fromNodeId,
                'to_node_id' => $toNodeId,
                'terrain_id' => $terrainId,
                'path' => $path,
                'distance_meters' => $distanceMeters,
                'is_directed' => $isDirected,
                'label' => $label,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map edge row is invalid', $exception);
        }
    }

    /**
     * Ребро по id.
     *
     * @param int $edgeId Id.
     *
     * @return MapEdgeRecord Ребро.
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если строка битая.
     */
    public function getById(int $edgeId): MapEdgeRecord
    {
        $row = $this->edgeRecords->getById($edgeId);
        if ($row === null) {
            throw new MapNotFoundException('Map edge was not found');
        }

        return MapEdgeRecord::fromNormalized($row);
    }

    /**
     * Рёбра варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapEdgeRecord> Рёбра.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByVariant(int $variantId): array
    {
        $listResult = $this->listResult(['=variant_id' => $variantId]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapEdgeRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Рёбра, касающиеся узла.
     *
     * @param int $nodeId Узел.
     *
     * @return array<int, MapEdgeRecord> Рёбра.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByNode(int $nodeId): array
    {
        $listResult = $this->listResult([
            'LOGIC' => 'OR',
            ['=from_node_id' => $nodeId],
            ['=to_node_id' => $nodeId],
        ]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapEdgeRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет поля ребра.
     *
     * @param int $edgeId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     * @throws MapInvalidException Если поле недопустимо.
     */
    public function update(int $edgeId, array $fields): void
    {
        try {
            $this->edgeRecords->update($edgeId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map edge was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map edge field is invalid', $exception);
        }
    }

    /**
     * Удаляет ребро.
     *
     * @param int $edgeId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function delete(int $edgeId): void
    {
        try {
            $this->edgeRecords->delete($edgeId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map edge was not found', $exception);
        }
    }

    /**
     * Удаляет рёбра узла.
     *
     * @param int $nodeId Узел.
     *
     * @return void
     *
     * @throws MapInvalidException Если удаление не удалось.
     */
    public function deleteByNode(int $nodeId): void
    {
        foreach ($this->findByNode($nodeId) as $edgeRecord) {
            $this->delete($edgeRecord->getId());
        }
    }

    /**
     * Читает рёбра по фильтру.
     *
     * @param array<int|string, mixed> $filter Фильтр.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function listResult(array $filter): ListResult
    {
        try {
            return $this->edgeRecords->getList(ListQuery::fromOptions([
                'filter' => $filter,
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map edge list is invalid', $exception);
        }
    }
}
