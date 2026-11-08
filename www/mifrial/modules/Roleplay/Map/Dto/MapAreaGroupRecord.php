<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

/**
 * Прочитанная строка `map_area_group`.
 */
final class MapAreaGroupRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $mapId Карта.
     * @param string $label Подпись группы.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $mapId,
        private readonly string $label,
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
     * Карта.
     *
     * @return int Id карты.
     */
    public function getMapId(): int
    {
        return $this->mapId;
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
