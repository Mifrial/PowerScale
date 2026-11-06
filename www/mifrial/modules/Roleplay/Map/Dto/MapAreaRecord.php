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
     * @param string $layer surface|object.
     * @param string $color Цвет области.
     * @param int $speed Проходимость: 0 — непроходимо.
     * @param string $label Подпись.
     * @param int|null $nodeId Привязанное место.
     * @param int $sortOrder Порядок внутри слоя.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $mapId,
        private readonly string $layer,
        private readonly string $color,
        private readonly int $speed,
        private readonly string $label,
        private readonly ?int $nodeId,
        private readonly int $sortOrder,
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
            MapValues::requireString($fields, 'color'),
            MapValues::requireInt($fields, 'speed'),
            MapValues::requireString($fields, 'label'),
            MapValues::optionalInt($fields, 'node_id'),
            MapValues::optionalInt($fields, 'sort_order') ?? 0,
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
     * @return string surface|object.
     */
    public function getLayer(): string
    {
        return $this->layer;
    }

    /**
     * Цвет.
     *
     * @return string Цвет.
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Проходимость.
     *
     * @return int Скорость.
     */
    public function getSpeed(): int
    {
        return $this->speed;
    }

    /**
     * Порядок внутри слоя.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
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
     * Место.
     *
     * @return int|null Id узла или null.
     */
    public function getNodeId(): ?int
    {
        return $this->nodeId;
    }
}
