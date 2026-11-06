<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Прочитанная строка `map_variant_edge`.
 */
final class MapEdgeRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $variantId Вариант.
     * @param int $fromNodeId Начало.
     * @param int $toNodeId Конец.
     * @param int $terrainId Террейн.
     * @param array<int, array{0: int, 1: int}> $path Полилиния; пусто — прямая.
     * @param int|null $distanceMeters Явное расстояние.
     * @param bool $isDirected Направленное.
     * @param string $label Подпись.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $variantId,
        private readonly int $fromNodeId,
        private readonly int $toNodeId,
        private readonly int $terrainId,
        private readonly array $path,
        private readonly ?int $distanceMeters,
        private readonly bool $isDirected,
        private readonly string $label,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws MapInvalidException Если полилиния битая.
     */
    public static function fromNormalized(array $fields): self
    {
        $path = MapValues::path($fields, 'path', 0, true);
        if ($path === null) {
            throw new MapInvalidException('Map edge has no path');
        }

        return new self(
            MapValues::requireInt($fields, 'id'),
            MapValues::requireInt($fields, 'variant_id'),
            MapValues::requireInt($fields, 'from_node_id'),
            MapValues::requireInt($fields, 'to_node_id'),
            MapValues::requireInt($fields, 'terrain_id'),
            $path,
            MapValues::optionalInt($fields, 'distance_meters'),
            MapValues::requireBool($fields, 'is_directed'),
            MapValues::requireString($fields, 'label'),
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
     * Начало.
     *
     * @return int Id узла.
     */
    public function getFromNodeId(): int
    {
        return $this->fromNodeId;
    }

    /**
     * Конец.
     *
     * @return int Id узла.
     */
    public function getToNodeId(): int
    {
        return $this->toNodeId;
    }

    /**
     * Террейн.
     *
     * @return int Id террейна.
     */
    public function getTerrainId(): int
    {
        return $this->terrainId;
    }

    /**
     * Полилиния.
     *
     * @return array<int, array{0: int, 1: int}> Точки; пусто — прямая.
     */
    public function getPath(): array
    {
        return $this->path;
    }

    /**
     * Явное расстояние.
     *
     * @return int|null Метры или null.
     */
    public function getDistanceMeters(): ?int
    {
        return $this->distanceMeters;
    }

    /**
     * Направленное.
     *
     * @return bool Флаг.
     */
    public function isDirected(): bool
    {
        return $this->isDirected;
    }

    /**
     * Подпись.
     *
     * @return string Подпись.
     */
    public function getLabel(): string
    {
        return $this->label;
    }
}
