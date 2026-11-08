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
     * @param string $layer Слой: surface или object.
     * @param string $color Цвет.
     * @param string $stroke Цвет границы; пусто — границы нет.
     * @param int $strokeWidth Толщина границы.
     * @param array<string, mixed> $decor Визуальный декор.
     * @param int $speed Проходимость.
     * @param string $label Подпись.
     * @param int|null $nodeId Место.
     * @param int $sortOrder Порядок внутри слоя.
     * @param int|null $groupId Группа областей.
     *
     * @return int Id.
     */
    public function addArea(
        int $mapId,
        string $layer,
        string $color,
        string $stroke,
        int $strokeWidth,
        array $decor,
        int $speed,
        string $label,
        ?int $nodeId,
        int $sortOrder,
        ?int $groupId = null,
    ): int {
        return $this->areaRepository->add(
            $mapId,
            $layer,
            $color,
            $stroke,
            $strokeWidth,
            $decor,
            $speed,
            $label,
            $nodeId,
            $sortOrder,
            $groupId,
        );
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
     * Ставит полигон.
     *
     * @param int $variantId Вариант.
     * @param int $areaId Область.
     * @param array<int, array{0: int, 1: int}> $path Полигон.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param bool $isPresent Присутствует.
     *
     * @return void
     */
    public function upsertShape(int $variantId, int $areaId, array $path, array $handles, bool $isPresent): void
    {
        $this->shapeRepository->upsert($variantId, $areaId, $path, $handles, $isPresent);
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
