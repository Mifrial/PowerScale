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
use Mifrial\Roleplay\Map\Dto\MapPlacementRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_variant_node`: размещение узлов по вариантам.
 */
final class MapPlacementRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $placementRecords Строки размещений.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $placementRecords,
    ) {
    }

    /**
     * Ставит или обновляет размещение.
     *
     * @param int $variantId Вариант.
     * @param int $nodeId Узел.
     * @param int $x X.
     * @param int $y Y.
     * @param bool $isPresent Присутствует.
     * @param string $labelOverride Подпись варианта.
     *
     * @return void
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function upsert(
        int $variantId,
        int $nodeId,
        int $x,
        int $y,
        bool $isPresent,
        string $labelOverride,
    ): void {
        $existing = $this->findOne($variantId, $nodeId);
        if ($existing !== null) {
            $this->update($existing->getId(), [
                'x' => $x,
                'y' => $y,
                'is_present' => $isPresent,
                'label_override' => $labelOverride,
            ]);

            return;
        }

        $this->insert($variantId, $nodeId, $x, $y, $isPresent, $labelOverride);
    }

    /**
     * Размещения варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapPlacementRecord> Размещения.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByVariant(int $variantId): array
    {
        $listResult = $this->listResult(['=variant_id' => $variantId]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapPlacementRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Размещения узла.
     *
     * @param int $nodeId Узел.
     *
     * @return array<int, MapPlacementRecord> Размещения.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByNode(int $nodeId): array
    {
        $listResult = $this->listResult(['=node_id' => $nodeId]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapPlacementRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Одно размещение.
     *
     * @param int $variantId Вариант.
     * @param int $nodeId Узел.
     *
     * @return MapPlacementRecord|null Размещение или null.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findOne(int $variantId, int $nodeId): ?MapPlacementRecord
    {
        $row = $this->placementRecords->getUnique(ListQuery::fromOptions([
            'filter' => [
                'LOGIC' => 'AND',
                ['=variant_id' => $variantId],
                ['=node_id' => $nodeId],
            ],
            'limit' => 1,
        ]));

        return $row === null ? null : MapPlacementRecord::fromNormalized($row);
    }

    /**
     * Удаляет размещения узла.
     *
     * @param int $nodeId Узел.
     *
     * @return void
     *
     * @throws MapInvalidException Если удаление не удалось.
     */
    public function deleteByNode(int $nodeId): void
    {
        foreach ($this->findByNode($nodeId) as $placementRecord) {
            $this->delete($placementRecord->getId());
        }
    }

    /**
     * Удаляет размещения варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return void
     *
     * @throws MapInvalidException Если удаление не удалось.
     */
    public function deleteByVariant(int $variantId): void
    {
        foreach ($this->findByVariant($variantId) as $placementRecord) {
            $this->delete($placementRecord->getId());
        }
    }

    /**
     * Вставляет размещение.
     *
     * @param int $variantId Вариант.
     * @param int $nodeId Узел.
     * @param int $x X.
     * @param int $y Y.
     * @param bool $isPresent Присутствует.
     * @param string $labelOverride Подпись.
     *
     * @return void
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    private function insert(
        int $variantId,
        int $nodeId,
        int $x,
        int $y,
        bool $isPresent,
        string $labelOverride,
    ): void {
        try {
            $this->placementRecords->add([
                'variant_id' => $variantId,
                'node_id' => $nodeId,
                'x' => $x,
                'y' => $y,
                'is_present' => $isPresent,
                'label_override' => $labelOverride,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map placement row is invalid', $exception);
        }
    }

    /**
     * Пишет поля размещения.
     *
     * @param int $placementId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле недопустимо.
     */
    private function update(int $placementId, array $fields): void
    {
        try {
            $this->placementRecords->update($placementId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map placement was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map placement field is invalid', $exception);
        }
    }

    /**
     * Удаляет строку.
     *
     * @param int $placementId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    private function delete(int $placementId): void
    {
        try {
            $this->placementRecords->delete($placementId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map placement was not found', $exception);
        }
    }

    /**
     * Читает размещения по фильтру.
     *
     * @param array<string, mixed> $filter Фильтр.
     *
     * @return ListResult Страница.
     *
     * @throws MapInvalidException Если запрос недопустим.
     */
    private function listResult(array $filter): ListResult
    {
        try {
            return $this->placementRecords->getList(ListQuery::fromOptions([
                'filter' => $filter,
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map placement list is invalid', $exception);
        }
    }
}
