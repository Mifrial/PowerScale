<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;
use Mifrial\Roleplay\Map\Exception\MapNotFoundException;

/**
 * Проверка областей, лент и рёбер снимка карты.
 */
final class MapGraphSaveValidator
{
    /**
     * Создаёт валидатор.
     *
     * @param MapSaveFields $fields Поля снимка.
     * @param MapSaveSets $sets Коллекции снимка.
     * @param MapTerrainService $terrainService Справочник террейнов.
     *
     * @return void
     */
    public function __construct(
        private readonly MapSaveFields $fields,
        private readonly MapSaveSets $sets,
        private readonly MapTerrainService $terrainService,
    ) {
    }

    /**
     * Области.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param array<int, array<string, mixed>> $variants Варианты.
     * @param array<int, array<string, mixed>> $nodes Узлы.
     *
     * @return array<int, array<string, mixed>> Области.
     *
     * @throws MapInvalidException Если области недопустимы.
     */
    public function areas(array $payload, array $variants, array $nodes): array
    {
        $variantIds = $this->sets->idSet($variants);
        $nodeIds = $this->sets->idSet($nodes);
        $areas = [];
        foreach ($this->sets->rows($payload, 'areas', 'map.areas') as $index => $row) {
            $areas[] = $this->areaRow($row, $index, $variantIds, $nodeIds);
        }

        $this->sets->assertUniqueIds($areas, 'areas');

        return $areas;
    }

    /**
     * Ленты.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param array<int, array<string, mixed>> $variants Варианты.
     *
     * @return array<int, array<string, mixed>> Ленты.
     *
     * @throws MapInvalidException Если ленты недопустимы.
     */
    public function lines(array $payload, array $variants): array
    {
        $variantIds = $this->sets->idSet($variants);
        $lines = [];
        foreach ($this->sets->rows($payload, 'lines', 'map.lines') as $index => $row) {
            $lines[] = $this->lineRow($row, $index, $variantIds);
        }

        $this->sets->assertUniqueIds($lines, 'lines');

        return $lines;
    }

    /**
     * Рёбра.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param array<int, array<string, mixed>> $variants Варианты.
     * @param array<int, array<string, mixed>> $nodes Узлы.
     *
     * @return array<int, array<string, mixed>> Рёбра.
     *
     * @throws MapInvalidException Если рёбра недопустимы.
     */
    public function edges(array $payload, array $variants, array $nodes): array
    {
        $variantIds = $this->sets->idSet($variants);
        $nodeIds = $this->sets->idSet($nodes);
        $scales = $this->sets->scales($variants);
        $edges = [];
        foreach ($this->sets->rows($payload, 'edges', 'map.edges') as $index => $row) {
            $edges[] = $this->edgeRow($row, $index, $variantIds, $nodeIds, $scales);
        }

        $this->sets->assertUniqueIds($edges, 'edges');

        return $edges;
    }

    /**
     * Одна область.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, true> $variantIds Варианты снимка.
     * @param array<int, true> $nodeIds Узлы снимка.
     *
     * @return array<string, mixed> Область.
     *
     * @throws MapInvalidException Если область недопустима.
     */
    private function areaRow(array $row, int $index, array $variantIds, array $nodeIds): array
    {
        $path = 'areas[' . $index . ']';
        $layer = $this->fields->stringValue($row, 'layer', $path . '.layer');
        if ($layer !== 'terrain' && $layer !== 'object') {
            throw new MapInvalidException($path . '.layer is invalid');
        }

        [$terrainId, $kind] = $this->areaKind($row, $path, $layer);
        $nodeId = $this->fields->optionalIdentifier($row, 'nodeId', $path . '.nodeId');
        if ($nodeId !== null && !isset($nodeIds[$nodeId])) {
            throw new MapInvalidException($path . '.nodeId is unknown');
        }

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'layer' => $layer,
            'terrainId' => $terrainId,
            'kind' => $kind,
            'label' => trim((string) $this->fields->optionalStringValue($row, 'label', $path . '.label')),
            'nodeId' => $nodeId,
            'blocksMovement' => $this->fields->boolValue($row, 'blocksMovement', $path . '.blocksMovement'),
            'shapes' => $this->shapes($row, $path, $variantIds),
        ];
    }

    /**
     * Террейн ландшафта или вид объекта.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param string $layer Слой.
     *
     * @return array{0: int|null, 1: string} Террейн и вид.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    private function areaKind(array $row, string $path, string $layer): array
    {
        if ($layer === 'terrain') {
            return [$this->terrainId($row, 'terrainCode', $path . '.terrainCode'), 'other'];
        }

        $kind = trim($this->fields->stringValue($row, 'kind', $path . '.kind'));
        if ($kind === '') {
            throw new MapInvalidException($path . '.kind is empty');
        }

        return [null, $kind];
    }

    /**
     * Формы области.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param array<int, true> $variantIds Варианты снимка.
     *
     * @return array<int, array<string, mixed>> Формы.
     *
     * @throws MapInvalidException Если форма недопустима.
     */
    private function shapes(array $row, string $path, array $variantIds): array
    {
        $shapes = [];
        foreach ($this->sets->rows($row, 'shapes', $path . '.shapes') as $index => $shape) {
            $shapePath = $path . '.shapes[' . $index . ']';
            $variantId = $this->fields->identifier($shape, 'variantId', $shapePath . '.variantId');
            $this->sets->assertVariantExists($variantId, $variantIds, $shapePath . '.variantId');
            $shapes[] = [
                'variantId' => $variantId,
                'path' => $this->fields->pointsValue($shape, 'path', $shapePath . '.path', 3),
                'isPresent' => $this->fields->boolValue($shape, 'isPresent', $shapePath . '.isPresent'),
            ];
        }

        return $shapes;
    }

    /**
     * Одна лента.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, true> $variantIds Варианты снимка.
     *
     * @return array<string, mixed> Лента.
     *
     * @throws MapInvalidException Если лента недопустима.
     */
    private function lineRow(array $row, int $index, array $variantIds): array
    {
        $path = 'lines[' . $index . ']';
        $variantId = $this->fields->identifier($row, 'variantId', $path . '.variantId');
        $this->sets->assertVariantExists($variantId, $variantIds, $path . '.variantId');
        $points = $this->fields->pointsValue($row, 'points', $path . '.points', 2);

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'variantId' => $variantId,
            'terrainId' => $this->terrainId($row, 'terrainCode', $path . '.terrainCode'),
            'points' => $points,
            'widths' => $this->fields->widthsValue($row, 'widths', $path . '.widths', count($points)),
            'handles' => $this->fields->handlesValue($row, 'handles', $path . '.handles', count($points)),
            'label' => trim((string) $this->fields->optionalStringValue($row, 'label', $path . '.label')),
            'isPresent' => $this->fields->boolValue($row, 'isPresent', $path . '.isPresent'),
        ];
    }

    /**
     * Одно ребро.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, true> $variantIds Варианты снимка.
     * @param array<int, true> $nodeIds Узлы снимка.
     * @param array<int, int|null> $scales Масштабы вариантов.
     *
     * @return array<string, mixed> Ребро.
     *
     * @throws MapInvalidException Если ребро недопустимо.
     */
    private function edgeRow(array $row, int $index, array $variantIds, array $nodeIds, array $scales): array
    {
        $path = 'edges[' . $index . ']';
        $variantId = $this->fields->identifier($row, 'variantId', $path . '.variantId');
        $this->sets->assertVariantExists($variantId, $variantIds, $path . '.variantId');
        $fromNodeId = $this->fields->identifier($row, 'fromNodeId', $path . '.fromNodeId');
        $toNodeId = $this->fields->identifier($row, 'toNodeId', $path . '.toNodeId');
        $this->assertEndpoints($fromNodeId, $toNodeId, $nodeIds, $path);

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'variantId' => $variantId,
            'fromNodeId' => $fromNodeId,
            'toNodeId' => $toNodeId,
            'terrainId' => $this->terrainId($row, 'terrainCode', $path . '.terrainCode'),
            'path' => $this->edgePath($row, $path),
            'distanceMeters' => $this->distance($row, $path, $variantId, $scales),
            'isDirected' => $this->fields->boolValue($row, 'isDirected', $path . '.isDirected'),
            'label' => trim((string) $this->fields->optionalStringValue($row, 'label', $path . '.label')),
        ];
    }

    /**
     * Узлы ребра существуют и различаются.
     *
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     * @param array<int, true> $nodeIds Узлы снимка.
     * @param string $path Путь.
     *
     * @return void
     *
     * @throws MapInvalidException Если узлы неверны.
     */
    private function assertEndpoints(int $fromNodeId, int $toNodeId, array $nodeIds, string $path): void
    {
        if (!isset($nodeIds[$fromNodeId])) {
            throw new MapInvalidException($path . '.fromNodeId is unknown');
        }

        if (!isset($nodeIds[$toNodeId])) {
            throw new MapInvalidException($path . '.toNodeId is unknown');
        }

        if ($fromNodeId === $toNodeId) {
            throw new MapInvalidException($path . ' connects a node to itself');
        }
    }

    /**
     * Полилиния ребра.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     *
     * @throws MapInvalidException Если точек ровно одна.
     */
    private function edgePath(array $row, string $path): array
    {
        $points = $this->fields->pointsValue($row, 'path', $path . '.path', 0, true);
        if (count($points) === 1) {
            throw new MapInvalidException($path . '.path has one point');
        }

        return $points;
    }

    /**
     * Расстояние ребра.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param int $variantId Вариант.
     * @param array<int, int|null> $scales Масштабы.
     *
     * @return int|null Метры или null.
     *
     * @throws MapInvalidException Если нет масштаба и нет расстояния.
     */
    private function distance(array $row, string $path, int $variantId, array $scales): ?int
    {
        $distance = $this->fields->optionalIntValue($row, 'distanceMeters', $path . '.distanceMeters', 0);
        if ($distance === null && ($scales[$variantId] ?? null) === null) {
            throw new MapInvalidException($path . '.distanceMeters is required without a scale');
        }

        return $distance;
    }

    /**
     * Террейн по коду.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $path Путь.
     *
     * @return int Id террейна.
     *
     * @throws MapInvalidException Если код неизвестен.
     */
    private function terrainId(array $row, string $key, string $path): int
    {
        $code = trim($this->fields->stringValue($row, $key, $path));
        if ($code === '') {
            throw new MapInvalidException($path . ' is empty');
        }

        try {
            return $this->terrainService->requireByCode($code)->getId();
        } catch (MapNotFoundException $exception) {
            throw new MapInvalidException($path . ' is unknown', $exception);
        }
    }
}
