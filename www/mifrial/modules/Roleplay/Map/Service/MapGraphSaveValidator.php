<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Проверка областей и лент снимка карты.
 */
final class MapGraphSaveValidator
{
    /**
     * Создаёт валидатор.
     *
     * @param MapSaveFields $fields Скалярные поля снимка.
     * @param MapSaveSets $sets Коллекции снимка.
     * @param MapSaveGeometryFields $geometryFields Геометрия ленты и контура.
     * @param MapSaveDecorFields $decorFields Декор ленты и области.
     *
     * @return void
     */
    public function __construct(
        private readonly MapSaveFields $fields,
        private readonly MapSaveSets $sets,
        private readonly MapSaveGeometryFields $geometryFields,
        private readonly MapSaveDecorFields $decorFields,
    ) {
    }

    /**
     * Группы областей.
     *
     * @param array<string|int, mixed> $payload Тело.
     *
     * @return array<int, array<string, mixed>> Группы.
     *
     * @throws MapInvalidException Если группы недопустимы.
     */
    public function groups(array $payload): array
    {
        $groups = [];
        foreach ($this->sets->rows($payload, 'groups', 'map.groups') as $index => $row) {
            $path = 'groups[' . $index . ']';
            $groups[] = [
                'id' => $this->fields->identifier($row, 'id', $path . '.id'),
                'label' => trim((string) $this->fields->optionalStringValue($row, 'label', $path . '.label')),
            ];
        }

        $this->sets->assertUniqueIds($groups, 'groups');

        return $groups;
    }

    /**
     * Области.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param array<int, array<string, mixed>> $variants Варианты.
     * @param array<int, array<string, mixed>> $nodes Узлы.
     * @param array<int, array<string, mixed>> $groups Группы.
     *
     * @return array<int, array<string, mixed>> Области.
     *
     * @throws MapInvalidException Если области недопустимы.
     */
    public function areas(array $payload, array $variants, array $nodes, array $groups): array
    {
        $variantIds = $this->sets->idSet($variants);
        $nodeIds = $this->sets->idSet($nodes);
        $groupIds = $this->sets->idSet($groups);
        $areas = [];
        foreach ($this->sets->rows($payload, 'areas', 'map.areas') as $index => $row) {
            $areas[] = $this->areaRow($row, $index, $variantIds, $nodeIds, $groupIds);
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
     * Одна область.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, true> $variantIds Варианты снимка.
     * @param array<int, true> $nodeIds Узлы снимка.
     * @param array<int, true> $groupIds Группы снимка.
     *
     * @return array<string, mixed> Область.
     *
     * @throws MapInvalidException Если область недопустима.
     */
    private function areaRow(array $row, int $index, array $variantIds, array $nodeIds, array $groupIds): array
    {
        $path = 'areas[' . $index . ']';

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'layer' => $this->areaLayer($row, $path),
            'color' => $this->fields->colorValue($row, 'color', $path . '.color'),
            'stroke' => array_key_exists('stroke', $row)
                ? $this->fields->strokeValue($row, 'stroke', $path . '.stroke')
                : '',
            'strokeWidth' => $this->areaStrokeWidth($row, $path),
            'decor' => $this->decorFields->areaDecorValue($row, 'decor', $path . '.decor'),
            'speed' => $this->fields->intValue($row, 'speed', $path . '.speed', 0, MapSpeed::MAX),
            'label' => trim((string) $this->fields->optionalStringValue($row, 'label', $path . '.label')),
            'nodeId' => $this->areaNodeId($row, $path, $nodeIds),
            'groupId' => $this->areaGroupId($row, $path, $groupIds),
            'sortOrder' => $this->fields->optionalIntValue($row, 'sortOrder', $path . '.sortOrder', 0) ?? 0,
            'shapes' => $this->shapes($row, $path, $variantIds),
        ];
    }

    /**
     * Слой области.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     *
     * @return string surface|object.
     *
     * @throws MapInvalidException Если слой недопустим.
     */
    private function areaLayer(array $row, string $path): string
    {
        $layer = $this->fields->stringValue($row, 'layer', $path . '.layer');
        if ($layer !== 'surface' && $layer !== 'object') {
            throw new MapInvalidException($path . '.layer is invalid');
        }

        return $layer;
    }

    /**
     * Толщина границы области.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     *
     * @return int Экранные пиксели.
     *
     * @throws MapInvalidException Если значение неверно.
     */
    private function areaStrokeWidth(array $row, string $path): int
    {
        if (!array_key_exists('strokeWidth', $row) || $row['strokeWidth'] === null) {
            return MapAreaStroke::DEFAULT_WIDTH;
        }

        return $this->fields->intValue(
            $row,
            'strokeWidth',
            $path . '.strokeWidth',
            MapAreaStroke::MIN_WIDTH,
            MapAreaStroke::MAX_WIDTH,
        );
    }

    /**
     * Ссылка области на место.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param array<int, true> $nodeIds Узлы снимка.
     *
     * @return int|null Id узла или null.
     *
     * @throws MapInvalidException Если ссылка неизвестна.
     */
    private function areaNodeId(array $row, string $path, array $nodeIds): ?int
    {
        $nodeId = $this->fields->optionalIdentifier($row, 'nodeId', $path . '.nodeId');
        if ($nodeId !== null && !isset($nodeIds[$nodeId])) {
            throw new MapInvalidException($path . '.nodeId is unknown');
        }

        return $nodeId;
    }

    /**
     * Ссылка области на группу.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param array<int, true> $groupIds Группы снимка.
     *
     * @return int|null Id группы или null.
     *
     * @throws MapInvalidException Если ссылка неизвестна.
     */
    private function areaGroupId(array $row, string $path, array $groupIds): ?int
    {
        $groupId = $this->fields->optionalIdentifier($row, 'groupId', $path . '.groupId');
        if ($groupId !== null && !isset($groupIds[$groupId])) {
            throw new MapInvalidException($path . '.groupId is unknown');
        }

        return $groupId;
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
            $points = $this->geometryFields->pointsValue($shape, 'path', $shapePath . '.path', 3);
            $shapes[] = [
                'variantId' => $variantId,
                'path' => $points,
                'handles' => $this->geometryFields->handlesValue($shape, 'handles', $shapePath . '.handles', count($points)),
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
        $points = $this->geometryFields->pointsValue($row, 'points', $path . '.points', 2);

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'variantId' => $variantId,
            'color' => $this->fields->colorValue($row, 'color', $path . '.color'),
            'speed' => $this->fields->intValue($row, 'speed', $path . '.speed', 0, MapSpeed::MAX),
            'points' => $points,
            'widths' => $this->geometryFields->widthsValue($row, 'widths', $path . '.widths', count($points)),
            'handles' => $this->geometryFields->handlesValue($row, 'handles', $path . '.handles', count($points)),
            'segments' => $this->geometryFields->segmentsValue($row, 'segments', $path . '.segments', count($points) - 1),
            'colorBlend' => array_key_exists('colorBlend', $row)
                ? $this->fields->intValue($row, 'colorBlend', $path . '.colorBlend', 0, 1000)
                : 16,
            'decor' => $this->decorFields->decorValue($row, 'decor', $path . '.decor'),
            'label' => trim((string) $this->fields->optionalStringValue($row, 'label', $path . '.label')),
            'isPresent' => $this->fields->boolValue($row, 'isPresent', $path . '.isPresent'),
            'sortOrder' => $this->fields->optionalIntValue($row, 'sortOrder', $path . '.sortOrder', 0) ?? 0,
        ];
    }
}
