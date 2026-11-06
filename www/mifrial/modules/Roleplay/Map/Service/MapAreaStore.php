<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Repository\MapAreaRepository;
use Mifrial\Roleplay\Map\Repository\MapAreaShapeRepository;

/**
 * Области и их полигоны: один вход для редактора.
 */
final class MapAreaStore
{
    /**
     * Создаёт стор.
     *
     * @param MapAreaRepository $areaRepository Области.
     * @param MapAreaShapeRepository $shapeRepository Полигоны.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAreaRepository $areaRepository,
        private readonly MapAreaShapeRepository $shapeRepository,
    ) {
    }

    /**
     * Области карты.
     *
     * @param int $mapId Карта.
     *
     * @return array<int, MapAreaRecord> Области.
     */
    public function findAreas(int $mapId): array
    {
        return $this->areaRepository->findByMap($mapId);
    }

    /**
     * Полигоны варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return array<int, MapAreaShapeRecord> Полигоны.
     */
    public function findShapes(int $variantId): array
    {
        return $this->shapeRepository->findByVariant($variantId);
    }

    /**
     * Область в границах карты.
     *
     * @param int $mapId Карта.
     * @param int $areaId Область.
     *
     * @return MapAreaRecord Область.
     */
    public function findArea(int $mapId, int $areaId): MapAreaRecord
    {
        return $this->areaRepository->getForMap($mapId, $areaId);
    }

    /**
     * Полигон области в варианте.
     *
     * @param int $variantId Вариант.
     * @param int $areaId Область.
     *
     * @return MapAreaShapeRecord|null Полигон или null.
     */
    public function findShape(int $variantId, int $areaId): ?MapAreaShapeRecord
    {
        return $this->shapeRepository->findOne($variantId, $areaId);
    }

    /**
     * Добавляет область.
     *
     * @param int $mapId Карта.
     * @param string $layer Terrain|object.
     * @param int|null $terrainId Террейн.
     * @param string $kind Вид объекта.
     * @param string $label Подпись.
     * @param int|null $nodeId POI.
     * @param bool $blocksMovement Непроходим.
     *
     * @return int Id.
     */
    public function addArea(
        int $mapId,
        string $layer,
        ?int $terrainId,
        string $kind,
        string $label,
        ?int $nodeId,
        bool $blocksMovement,
    ): int {
        return $this->areaRepository->add($mapId, $layer, $terrainId, $kind, $label, $nodeId, $blocksMovement);
    }

    /**
     * Пишет поля области.
     *
     * @param int $areaId Область.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     */
    public function updateArea(int $areaId, array $fields): void
    {
        $this->areaRepository->update($areaId, $fields);
    }

    /**
     * Ставит полигон.
     *
     * @param int $variantId Вариант.
     * @param int $areaId Область.
     * @param array<int, array{0: int, 1: int}> $path Полигон.
     * @param bool $isPresent Присутствует.
     *
     * @return void
     */
    public function upsertShape(int $variantId, int $areaId, array $path, bool $isPresent): void
    {
        $this->shapeRepository->upsert($variantId, $areaId, $path, $isPresent);
    }

    /**
     * Удаляет область и её полигоны.
     *
     * @param int $areaId Область.
     *
     * @return void
     */
    public function deleteArea(int $areaId): void
    {
        $this->shapeRepository->deleteByArea($areaId);
        $this->areaRepository->delete($areaId);
    }

    /**
     * Удаляет формы варианта.
     *
     * @param int $variantId Вариант.
     *
     * @return void
     */
    public function deleteShapesByVariant(int $variantId): void
    {
        $this->shapeRepository->deleteByVariant($variantId);
    }
}
