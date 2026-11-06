<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

/**
 * Прочитанная строка `map_node`.
 */
final class MapNodeRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $mapId Карта.
     * @param string $name Имя.
     * @param string $shortInfo Короткая информация.
     * @param string $publicInfo Раскрываемая информация.
     * @param string $gmInfo Информация ведущего.
     * @param int|null $linkedMapId Связанная карта.
     * @param string $icon Иконка.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $mapId,
        private readonly string $name,
        private readonly string $shortInfo,
        private readonly string $publicInfo,
        private readonly string $gmInfo,
        private readonly ?int $linkedMapId,
        private readonly string $icon,
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
            MapValues::requireString($fields, 'name'),
            MapValues::requireString($fields, 'short_info'),
            MapValues::requireString($fields, 'public_info'),
            MapValues::requireString($fields, 'gm_info'),
            MapValues::optionalInt($fields, 'linked_map_id'),
            MapValues::requireString($fields, 'icon'),
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
     * Имя.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Короткая информация.
     *
     * @return string Текст.
     */
    public function getShortInfo(): string
    {
        return $this->shortInfo;
    }

    /**
     * Раскрываемая информация.
     *
     * @return string Текст.
     */
    public function getPublicInfo(): string
    {
        return $this->publicInfo;
    }

    /**
     * Информация ведущего.
     *
     * @return string Текст.
     */
    public function getGmInfo(): string
    {
        return $this->gmInfo;
    }

    /**
     * Связанная карта.
     *
     * @return int|null Id карты или null.
     */
    public function getLinkedMapId(): ?int
    {
        return $this->linkedMapId;
    }

    /**
     * Иконка.
     *
     * @return string Код иконки.
     */
    public function getIcon(): string
    {
        return $this->icon;
    }
}
