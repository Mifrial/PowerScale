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
     * Добавляет пачку областей.
     *
     * @param int $mapId Карта.
     * @param array<int, array<string, mixed>> $rows Строки.
     *
     * @return array<int, int> Id в порядке входа.
     */
    public function addAreas(int $mapId, array $rows): array
    {
        return $this->areaRepository->addMany($mapId, $rows);
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
     * Вставляет пачку полигонов.
     *
     * @param array<int, array{variantId: int, areaId: int, path: array<int, array{0: int, 1: int}>, handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>, isPresent: bool}> $rows Полигоны.
     *
     * @return array<int, int> Id в порядке входа.
     */
    public function addShapes(array $rows): array
    {
        return $this->shapeRepository->addMany($rows);
    }

    /**
     * Пишет существующий полигон.
     *
     * @param int $shapeId Id.
     * @param array<int, array{0: int, 1: int}> $path Полигон.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки.
     * @param bool $isPresent Присутствует.
     *
     * @return void
     */
    public function updateShape(int $shapeId, array $path, array $handles, bool $isPresent): void
    {
        $this->shapeRepository->updateShape($shapeId, $path, $handles, $isPresent);
    }

    /**
     * Удаляет полигон по id.
     *
     * @param int $shapeId Id.
     *
     * @return void
     */
    public function deleteShape(int $shapeId): void
    {
        $this->shapeRepository->deleteShape($shapeId);
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

}
