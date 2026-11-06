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
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Строки `map_variant_area`: полигоны областей по вариантам.
 */
final class MapAreaShapeRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $shapeRecords Строки форм.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $shapeRecords,
    ) {
    }

    /**
     * Ставит или обновляет полигон.
     *
     * @param int $variantId Вариант.
     * @param int $areaId Область.
     * @param array<int, array{0: int, 1: int}> $path Полигон.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param bool $isPresent Присутствует.
     *
     * @return void
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    public function upsert(int $variantId, int $areaId, array $path, array $handles, bool $isPresent): void
    {
        $existing = $this->findOne($variantId, $areaId);
        if ($existing !== null) {
            $this->update($existing->getId(), ['path' => $path, 'handles' => $handles, 'is_present' => $isPresent]);

            return;
        }

        try {
            $this->shapeRecords->add([
                'variant_id' => $variantId,
                'area_id' => $areaId,
                'path' => $path,
                'handles' => $handles,
                'is_present' => $isPresent,
            ]);
        } catch (FieldInvalidException | SmartMapInvalidException | RowWriteFailedException $exception) {
            throw new MapInvalidException('Map area shape row is invalid', $exception);
        }
    }

    /**
     * Формы варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapAreaShapeRecord> Формы.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByVariant(int $variantId): array
    {
        $listResult = $this->listResult(['=variant_id' => $variantId]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapAreaShapeRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Формы области.
     *
     * @param int $areaId Область.
     *
     * @return array<int, MapAreaShapeRecord> Формы.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findByArea(int $areaId): array
    {
        $listResult = $this->listResult(['=area_id' => $areaId]);
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MapAreaShapeRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Одна форма.
     *
     * @param int $variantId Вариант.
     * @param int $areaId Область.
     *
     * @return MapAreaShapeRecord|null Форма или null.
     *
     * @throws MapInvalidException Если выборка битая.
     */
    public function findOne(int $variantId, int $areaId): ?MapAreaShapeRecord
    {
        $row = $this->shapeRecords->getUnique(ListQuery::fromOptions([
            'filter' => [
                'LOGIC' => 'AND',
                ['=variant_id' => $variantId],
                ['=area_id' => $areaId],
            ],
            'limit' => 1,
        ]));

        return $row === null ? null : MapAreaShapeRecord::fromNormalized($row);
    }

    /**
     * Удаляет формы области.
     *
     * @param int $areaId Область.
     *
     * @return void
     *
     * @throws MapInvalidException Если удаление не удалось.
     */
    public function deleteByArea(int $areaId): void
    {
        foreach ($this->findByArea($areaId) as $shapeRecord) {
            $this->delete($shapeRecord->getId());
        }
    }

    /**
     * Удаляет все формы варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    public function deleteByVariant(int $variantId): void
    {
        foreach ($this->findByVariant($variantId) as $shapeRecord) {
            $this->delete($shapeRecord->getId());
        }
    }

    /**
     * Пишет поля формы.
     *
     * @param int $shapeId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле недопустимо.
     */
    private function update(int $shapeId, array $fields): void
    {
        try {
            $this->shapeRecords->update($shapeId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map area shape was not found', $exception);
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map area shape field is invalid', $exception);
        }
    }

    /**
     * Удаляет строку.
     *
     * @param int $shapeId Id.
     *
     * @return void
     *
     * @throws MapNotFoundException Если строки нет.
     */
    private function delete(int $shapeId): void
    {
        try {
            $this->shapeRecords->delete($shapeId);
        } catch (RowNotFoundException $exception) {
            throw new MapNotFoundException('Map area shape was not found', $exception);
        }
    }

    /**
     * Читает формы по фильтру.
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
            return $this->shapeRecords->getList(ListQuery::fromOptions([
                'filter' => $filter,
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldInvalidException | SmartMapInvalidException $exception) {
            throw new MapInvalidException('Map area shape list is invalid', $exception);
        }
    }
}
