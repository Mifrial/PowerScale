<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

use Mifrial\Roleplay\Map\Service\MapAreaStroke;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Геттеры полной строки области; параметры ctor — поля, не порты. Сводить
// доступ к колонкам в структуру значило бы прятать смысл строки.

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
     * @param string $stroke Цвет границы; пусто — границы нет.
     * @param int $strokeWidth Толщина границы, экранные пиксели.
     * @param array<string, mixed> $decor Визуальный декор; пусто — без декора.
     * @param int $speed Проходимость: 0 — непроходимо.
     * @param string $label Подпись.
     * @param int|null $nodeId Привязанное место.
     * @param int $sortOrder Порядок внутри слоя.
     * @param int|null $groupId Группа областей.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $mapId,
        private readonly string $layer,
        private readonly string $color,
        private readonly string $stroke,
        private readonly int $strokeWidth,
        private readonly array $decor,
        private readonly int $speed,
        private readonly string $label,
        private readonly ?int $nodeId,
        private readonly int $sortOrder,
        private readonly ?int $groupId,
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
            MapValues::optionalString($fields, 'stroke') ?? '',
            MapValues::optionalInt($fields, 'stroke_width') ?: MapAreaStroke::DEFAULT_WIDTH,
            MapValues::areaDecor($fields['decor'] ?? null),
            MapValues::requireInt($fields, 'speed'),
            MapValues::requireString($fields, 'label'),
            MapValues::optionalInt($fields, 'node_id'),
            MapValues::optionalInt($fields, 'sort_order') ?? 0,
            MapValues::optionalInt($fields, 'group_id'),
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
     * Цвет границы.
     *
     * @return string Цвет `#rrggbb` или пустая строка.
     */
    public function getStroke(): string
    {
        return $this->stroke;
    }

    /**
     * Толщина границы.
     *
     * @return int Экранные пиксели.
     */
    public function getStrokeWidth(): int
    {
        return $this->strokeWidth;
    }

    /**
     * Визуальный декор.
     *
     * @return array<string, mixed> Декор; пусто — без декора.
     */
    public function getDecor(): array
    {
        return $this->decor;
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

    /**
     * Группа областей.
     *
     * @return int|null Id группы или null.
     */
    public function getGroupId(): ?int
    {
        return $this->groupId;
    }
}
