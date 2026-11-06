<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

/**
 * Прочитанная строка `map_terrain`.
 */
final class MapTerrainRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param string $code Код.
     * @param string $label Подпись.
     * @param int $speedFactor Процент скорости.
     * @param string $color Цвет.
     * @param int $sortOrder Порядок.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $label,
        private readonly int $speedFactor,
        private readonly string $color,
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
            MapValues::requireString($fields, 'code'),
            MapValues::requireString($fields, 'label'),
            MapValues::requireInt($fields, 'speed_factor'),
            MapValues::requireString($fields, 'color'),
            MapValues::requireInt($fields, 'sort_order'),
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
     * Код.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
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
     * Процент скорости.
     *
     * @return int 0..100.
     */
    public function getSpeedFactor(): int
    {
        return $this->speedFactor;
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
     * Порядок.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
