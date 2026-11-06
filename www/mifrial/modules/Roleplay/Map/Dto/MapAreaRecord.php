<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Прочитанная строка `map_area`.
 */
final class MapAreaRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $mapId Карта.
     * @param string $layer terrain|object.
     * @param int|null $terrainId Террейн ландшафта.
     * @param string $kind Вид объекта.
     * @param string $label Подпись.
     * @param int|null $nodeId Привязанный POI.
     * @param bool $blocksMovement Объект непроходим.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $mapId,
        private readonly string $layer,
        private readonly ?int $terrainId,
        private readonly string $kind,
        private readonly string $label,
        private readonly ?int $nodeId,
        private readonly bool $blocksMovement,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     */
    public static function fromNormalized(array $fields): self
    {
        return new self(
            MapValues::requireInt($fields, 'id'),
            MapValues::requireInt($fields, 'map_id'),
            MapValues::requireString($fields, 'layer'),
            MapValues::optionalInt($fields, 'terrain_id'),
            MapValues::requireString($fields, 'kind'),
            MapValues::requireString($fields, 'label'),
            MapValues::optionalInt($fields, 'node_id'),
            MapValues::requireBool($fields, 'blocks_movement'),
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
     * Карта.
     *
     * @return int Id карты.
     */
    public function getMapId(): int
    {
        return $this->mapId;
    }

    /**
     * Слой.
     *
     * @return string terrain|object.
     */
    public function getLayer(): string
    {
        return $this->layer;
    }

    /**
     * Террейн.
     *
     * @return int|null Id террейна или null.
     */
    public function getTerrainId(): ?int
    {
        return $this->terrainId;
    }

    /**
     * Вид.
     *
     * @return string Вид.
     */
    public function getKind(): string
    {
        return $this->kind;
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

    /**
     * Точка интереса.
     *
     * @return int|null Id узла или null.
     */
    public function getNodeId(): ?int
    {
        return $this->nodeId;
    }

    /**
     * Непроходим.
     *
     * @return bool Флаг.
     */
    public function blocksMovement(): bool
    {
        return $this->blocksMovement;
    }
}
