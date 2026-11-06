<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Exception\FileException;
use Mifrial\Core\File\Interface\Service\IFiles;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Проверка карты, вариантов и узлов перед публикацией.
 */
final class MapSaveValidator
{
    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Создаёт валидатор.
     *
     * @param MapSaveFields $fields Поля снимка.
     * @param MapSaveSets $sets Коллекции снимка.
     * @param IFiles $files Файлы подложек.
     * @param MapGraphSaveValidator $graphValidator Области, ленты и рёбра.
     *
     * @return void
     */
    public function __construct(
        private readonly MapSaveFields $fields,
        private readonly MapSaveSets $sets,
        private readonly IFiles $files,
        private readonly MapGraphSaveValidator $graphValidator,
    ) {
    }

    /**
     * Проверяет снимок.
     *
     * @param array<string|int, mixed> $payload Тело `map`.
     *
     * @return array<string, mixed> Нормализованные данные.
     *
     * @throws MapInvalidException Если снимок недопустим.
     */
    public function validate(array $payload): array
    {
        $variants = $this->variants($payload);
        $nodes = $this->nodes($payload, $variants);

        return $this->mapFields($payload) + [
            'variants' => $variants,
            'nodes' => $nodes,
            'areas' => $this->graphValidator->areas($payload, $variants, $nodes),
            'lines' => $this->graphValidator->lines($payload, $variants),
            'edges' => $this->graphValidator->edges($payload, $variants, $nodes),
        ];
    }

    /**
     * Поля карты.
     *
     * @param array<string|int, mixed> $payload Тело.
     *
     * @return array<string, mixed> Поля.
     *
     * @throws MapInvalidException Если поля недопустимы.
     */
    private function mapFields(array $payload): array
    {
        $name = trim($this->fields->stringValue($payload, 'name', 'map.name'));
        if ($name === '') {
            throw new MapInvalidException('map.name is empty');
        }

        $visibility = $this->fields->stringValue($payload, 'visibility', 'map.visibility');
        if ($visibility !== 'public' && $visibility !== 'private') {
            throw new MapInvalidException('map.visibility is invalid');
        }

        return [
            'name' => $name,
            'description' => trim($this->fields->stringValue($payload, 'description', 'map.description')),
            'visibility' => $visibility,
        ];
    }

    /**
     * Варианты.
     *
     * @param array<string|int, mixed> $payload Тело.
     *
     * @return array<int, array<string, mixed>> Варианты.
     *
     * @throws MapInvalidException Если варианты недопустимы.
     */
    private function variants(array $payload): array
    {
        $rows = $this->sets->rows($payload, 'variants', 'map.variants');
        if ($rows === []) {
            throw new MapInvalidException('map.variants is empty');
        }

        $variants = [];
        foreach ($rows as $index => $row) {
            $variants[] = $this->variantRow($row, $index);
        }

        $this->assertVariantSet($variants);

        return $variants;
    }

    /**
     * Один вариант.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     *
     * @return array<string, mixed> Вариант.
     *
     * @throws MapInvalidException Если вариант недопустим.
     */
    private function variantRow(array $row, int $index): array
    {
        $path = 'variants[' . $index . ']';
        $label = trim($this->fields->stringValue($row, 'label', $path . '.label'));
        if ($label === '') {
            throw new MapInvalidException($path . '.label is empty');
        }

        $file = $this->resolveFile(
            $this->fields->optionalStringValue($row, 'filePublicId', $path . '.filePublicId'),
            $path . '.filePublicId',
        );

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'label' => $label,
            'fromMinutes' => $this->fields->optionalIntValue($row, 'fromMinutes', $path . '.fromMinutes', 0),
            'sortOrder' => $this->fields->intValue($row, 'sortOrder', $path . '.sortOrder', 0),
            'baseFileId' => $file?->getId(),
            'width' => $file?->getWidth(),
            'height' => $file?->getHeight(),
            'millimetersPerPixel' => $this->fields->optionalIntValue(
                $row,
                'millimetersPerPixel',
                $path . '.millimetersPerPixel',
                1,
            ),
            'isDefault' => $this->fields->boolValue($row, 'isDefault', $path . '.isDefault'),
        ];
    }

    /**
     * Ровно один default и уникальные подписи.
     *
     * @param array<int, array<string, mixed>> $variants Варианты.
     *
     * @return void
     *
     * @throws MapInvalidException Если набор неверен.
     */
    private function assertVariantSet(array $variants): void
    {
        $labels = [];
        $defaults = 0;
        foreach ($variants as $variant) {
            if (isset($labels[$variant['label']])) {
                throw new MapInvalidException('map.variants have a duplicate label');
            }

            $labels[$variant['label']] = true;
            if ($variant['isDefault']) {
                $defaults += 1;
            }
        }

        if ($defaults !== 1) {
            throw new MapInvalidException('map.variants must have exactly one default');
        }

        $this->sets->assertUniqueIds($variants, 'variants');
    }

    /**
     * Узлы.
     *
     * @param array<string|int, mixed> $payload Тело.
     * @param array<int, array<string, mixed>> $variants Варианты.
     *
     * @return array<int, array<string, mixed>> Узлы.
     *
     * @throws MapInvalidException Если узлы недопустимы.
     */
    private function nodes(array $payload, array $variants): array
    {
        $variantIds = $this->sets->idSet($variants);
        $nodes = [];
        foreach ($this->sets->rows($payload, 'nodes', 'map.nodes') as $index => $row) {
            $nodes[] = $this->nodeRow($row, $index, $variantIds);
        }

        $this->sets->assertUniqueIds($nodes, 'nodes');

        return $nodes;
    }

    /**
     * Один узел.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param int $index Индекс.
     * @param array<int, true> $variantIds Варианты снимка.
     *
     * @return array<string, mixed> Узел.
     *
     * @throws MapInvalidException Если узел недопустим.
     */
    private function nodeRow(array $row, int $index, array $variantIds): array
    {
        $path = 'nodes[' . $index . ']';
        $isPoi = $this->fields->boolValue($row, 'isPoi', $path . '.isPoi');
        $name = trim($this->fields->stringValue($row, 'name', $path . '.name'));
        if ($isPoi && $name === '') {
            throw new MapInvalidException($path . '.name is empty for a POI');
        }

        return [
            'id' => $this->fields->identifier($row, 'id', $path . '.id'),
            'isPoi' => $isPoi,
            'name' => $name,
            'shortInfo' => trim((string) $this->fields->optionalStringValue($row, 'shortInfo', $path . '.shortInfo')),
            'publicInfo' => trim((string) $this->fields->optionalStringValue(
                $row,
                'publicInfo',
                $path . '.publicInfo',
            )),
            'gmInfo' => trim((string) $this->fields->optionalStringValue($row, 'gmInfo', $path . '.gmInfo')),
            'linkedMapId' => $this->fields->optionalIntValue($row, 'linkedMapId', $path . '.linkedMapId', 1),
            'icon' => (string) $this->fields->optionalStringValue($row, 'icon', $path . '.icon'),
            'placements' => $this->placements($row, $path, $variantIds),
        ];
    }

    /**
     * Размещения узла.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param array<int, true> $variantIds Варианты снимка.
     *
     * @return array<int, array<string, mixed>> Размещения.
     *
     * @throws MapInvalidException Если размещение недопустимо.
     */
    private function placements(array $row, string $path, array $variantIds): array
    {
        $placements = [];
        foreach ($this->sets->rows($row, 'placements', $path . '.placements') as $index => $placement) {
            $placements[] = $this->placementRow($placement, $path . '.placements[' . $index . ']', $variantIds);
        }

        return $placements;
    }

    /**
     * Одно размещение.
     *
     * @param array<string|int, mixed> $row Строка.
     * @param string $path Путь.
     * @param array<int, true> $variantIds Варианты снимка.
     *
     * @return array<string, mixed> Размещение.
     *
     * @throws MapInvalidException Если размещение недопустимо.
     */
    private function placementRow(array $row, string $path, array $variantIds): array
    {
        $variantId = $this->fields->identifier($row, 'variantId', $path . '.variantId');
        $this->sets->assertVariantExists($variantId, $variantIds, $path . '.variantId');

        return [
            'variantId' => $variantId,
            'x' => $this->fields->intValue($row, 'x', $path . '.x', 0, 20000),
            'y' => $this->fields->intValue($row, 'y', $path . '.y', 0, 20000),
            'isPresent' => $this->fields->boolValue($row, 'isPresent', $path . '.isPresent'),
            'labelOverride' => (string) $this->fields->optionalStringValue(
                $row,
                'labelOverride',
                $path . '.labelOverride',
            ),
        ];
    }

    /**
     * Подложка варианта.
     *
     * @param string|null $publicId Внешний id.
     * @param string $path Путь.
     *
     * @return FileRecord|null Файл или null.
     *
     * @throws MapInvalidException Если файл недоступен или не картинка.
     */
    private function resolveFile(?string $publicId, string $path): ?FileRecord
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        try {
            $fileRecord = $this->files->requireOwned($publicId);
        } catch (FileException $exception) {
            throw new MapInvalidException($path . ' is not available', $exception);
        }

        if ($fileRecord->isArchived()) {
            throw new MapInvalidException($path . ' is archived');
        }

        if (!in_array($fileRecord->getMime(), self::ALLOWED_MIME, true)) {
            throw new MapInvalidException($path . ' must be png, jpeg or webp');
        }

        if ($fileRecord->getWidth() === null || $fileRecord->getHeight() === null) {
            throw new MapInvalidException($path . ' has no dimensions');
        }

        return $fileRecord;
    }
}
