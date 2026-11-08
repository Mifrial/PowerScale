<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Прочитанная строка `map_variant_area`.
 */
final class MapAreaShapeRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $variantId Вариант.
     * @param int $areaId Область.
     * @param array<int, array{0: int, 1: int}> $path Полигон.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param bool $isPresent Область есть в варианте.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $variantId,
        private readonly int $areaId,
        private readonly array $path,
        private readonly array $handles,
        private readonly bool $isPresent,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws MapInvalidException Если полигон битый.
     */
    public static function fromNormalized(array $fields): self
    {
        $path = MapGeometryValues::path($fields, 'path', 3, true);
        if ($path === null) {
            throw new MapInvalidException('Map area shape has no path');
        }

        return new self(
            MapValues::requireInt($fields, 'id'),
            MapValues::requireInt($fields, 'variant_id'),
            MapValues::requireInt($fields, 'area_id'),
            $path,
            MapGeometryValues::handles($fields['handles'] ?? null, count($path)),
            MapValues::requireBool($fields, 'is_present'),
        );
    }

    /**
     * Идентификатор.
     *
     * @return int Id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Вариант.
     *
     * @return int Id варианта.
     */
    public function getVariantId(): int
    {
        return $this->variantId;
    }

    /**
     * Область.
     *
     * @return int Id области.
     */
    public function getAreaId(): int
    {
        return $this->areaId;
    }

    /**
     * Полигон.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * Ручки Безье.
     *
     * @return array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> Ручки.
     */
    public function getHandles(): array
    {
        return $this->handles;
    }

    /**
     * Присутствует.
     *
     * @return bool Флаг.
     */
    public function isPresent(): bool
    {
        return $this->isPresent;
    }
}
