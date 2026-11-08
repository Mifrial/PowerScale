<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Roleplay\Map\Dto\MapAreaRecord;
use Mifrial\Roleplay\Map\Dto\MapAreaShapeRecord;
use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Dto\MapRoute;
use Mifrial\Roleplay\Map\Exception\MapNoRouteException;

/**
 * Маршрут по проходимой зоне варианта.
 */
final class MapRouteService
{
    /**
     * Создаёт сервис.
     *
     * @param MapAccessService $accessService Доступ.
     * @param MapVariantService $variantService Варианты.
     * @param MapAreaStore $areaStore Области.
     * @param MapLineStore $lineStore Ленты.
     * @param MapRouteGrid $grid Сетка.
     * @param MapRouteFinder $finder Поиск маршрута.
     *
     * @return void
     */
    public function __construct(
        private readonly MapAccessService $accessService,
        private readonly MapVariantService $variantService,
        private readonly MapAreaStore $areaStore,
        private readonly MapLineStore $lineStore,
        private readonly MapRouteGrid $grid,
        private readonly MapRouteFinder $finder,
    ) {
    }

    /**
     * Строит маршрут между точками.
     *
     * @param RequestActor $actor Актор.
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     * @param int $fromX Начало X.
     * @param int $fromY Начало Y.
     * @param int $toX Конец X.
     * @param int $toY Конец Y.
     *
     * @return MapRoute Маршрут.
     *
     * @throws MapNoRouteException Если пути нет.
     */
    public function route(
        RequestActor $actor,
        int $mapId,
        int $variantId,
        int $fromX,
        int $fromY,
        int $toX,
        int $toY,
    ): MapRoute {
        $this->accessService->requireMapForView($actor, $mapId);
        $variantRecord = $this->variantService->requireVariant($mapId, $variantId);
        [$surfaces, $objects] = $this->items($mapId, $variantId);
        $width = $variantRecord->getWidth() ?? 1200;
        $height = $variantRecord->getHeight() ?? 900;
        $ribbons = $this->ribbons($this->lineStore->findLines($variantId));
        $field = $this->grid->build($width, $height, $surfaces, $ribbons, $objects);
        $route = $this->finder->route(
            $field,
            [$this->clamp($fromX, 0, $width), $this->clamp($fromY, 0, $height)],
            [$this->clamp($toX, 0, $width), $this->clamp($toY, 0, $height)],
        );

        return $this->response($route, $variantRecord->getMillimetersPerPixel());
    }

    /**
     * Геометрия областей варианта для сетки.
     *
     * @param int $mapId Карта.
     * @param int $variantId Вариант.
     *
     * @return array{
     *     0: array<int, array{id: int, path: array<int, array{0: int, 1: int}>,
     *         handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>,
     *         speed: int, sortOrder: int}>,
     *     1: array<int, array{id: int, path: array<int, array{0: int, 1: int}>,
     *         handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>,
     *         speed: int, sortOrder: int}>
     * } Поверхности и объекты.
     */
    private function items(int $mapId, int $variantId): array
    {
        $shapes = $this->indexShapes($this->areaStore->findShapes($variantId));
        $surfaces = [];
        $objects = [];
        foreach ($this->areaStore->findAreas($mapId) as $areaRecord) {
            $shape = $shapes[$areaRecord->getId()] ?? null;
            if (!$shape instanceof MapAreaShapeRecord || !$shape->isPresent() || count($shape->getPath()) < 3) {
                continue;
            }

            $item = [
                'id' => $areaRecord->getId(),
                'path' => $shape->getPath(),
                'handles' => $shape->getHandles(),
                'speed' => $areaRecord->getSpeed(),
                'sortOrder' => $areaRecord->getSortOrder(),
            ];
            if ($areaRecord->getLayer() === 'surface') {
                $surfaces[] = $item;

                continue;
            }

            $objects[] = $item;
        }

        return [$surfaces, $objects];
    }

    /**
     * Ленты варианта.
     *
     * @param array<int, MapLineRecord> $lines Ленты.
     *
     * @return array<int, array{
     *     id: int,
     *     points: array<int, array{0: int, 1: int}>,
     *     widths: array<int, int>,
     *     handles: array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}>,
     *     segmentSpeeds: array<int, int>,
     *     defaultSpeed: int,
     *     sortOrder: int
     * }> Ленты.
     */
    private function ribbons(array $lines): array
    {
        $ribbons = [];
        foreach ($lines as $lineRecord) {
            if (!$lineRecord->isPresent() || count($lineRecord->getPoints()) < 2) {
                continue;
            }

            $segmentSpeeds = [];
            foreach ($lineRecord->getSegments() as $segment) {
                $segmentSpeeds[] = $segment['speed'];
            }

            $ribbons[] = [
                'id' => $lineRecord->getId(),
                'points' => $lineRecord->getPoints(),
                'widths' => $lineRecord->getWidths(),
                'handles' => $lineRecord->getHandles(),
                'segmentSpeeds' => $segmentSpeeds,
                'defaultSpeed' => $lineRecord->getSpeed(),
                'sortOrder' => $lineRecord->getSortOrder(),
            ];
        }

        return $ribbons;
    }

    /**
     * Индекс полигонов.
     *
     * @param array<int, MapAreaShapeRecord> $shapes Полигоны.
     *
     * @return array<int, MapAreaShapeRecord> Id области → полигон.
     */
    private function indexShapes(array $shapes): array
    {
        $indexed = [];
        foreach ($shapes as $shapeRecord) {
            $indexed[$shapeRecord->getAreaId()] = $shapeRecord;
        }

        return $indexed;
    }

    /**
     * Ответ маршрута с метрикой варианта.
     *
     * @param array{points: array<int, array{0: int, 1: int}>, distancePixels: float, costPixels: float} $route Маршрут сетки.
     * @param int|null $scale Миллиметров на пиксель.
     *
     * @return MapRoute Маршрут.
     */
    private function response(array $route, ?int $scale): MapRoute
    {
        return new MapRoute(
            $route['points'],
            $route['distancePixels'],
            $route['costPixels'],
            $scale === null ? null : (int) round($route['distancePixels'] * $scale / 1000),
            $scale === null ? null : (int) round($route['costPixels'] * $scale / 1000),
        );
    }

    /**
     * Прижимает координату.
     *
     * @param int $value Значение.
     * @param int $min Минимум.
     * @param int $max Максимум.
     *
     * @return int Значение.
     */
    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
