<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `map_variant`.
 */
final class MapVariantRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $mapId Карта.
     * @param string $label Подпись эпохи.
     * @param int|null $fromMinutes Смещение от глобальной эпохи.
     * @param int $sortOrder Порядок.
     * @param int|null $baseFileId Подложка.
     * @param int|null $width Ширина подложки.
     * @param int|null $height Высота подложки.
     * @param int|null $millimetersPerPixel Масштаб.
     * @param bool $isDefault Вариант по умолчанию.
     * @param DateTime $createdAt Создан.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $mapId,
        private readonly string $label,
        private readonly ?int $fromMinutes,
        private readonly int $sortOrder,
        private readonly ?int $baseFileId,
        private readonly ?int $width,
        private readonly ?int $height,
        private readonly ?int $millimetersPerPixel,
        private readonly bool $isDefault,
        private readonly DateTime $createdAt,
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
            MapValues::optionalInt($fields, 'from_minutes'),
            MapValues::requireInt($fields, 'sort_order'),
            MapValues::optionalInt($fields, 'base_file_id'),
            MapValues::optionalInt($fields, 'width'),
            MapValues::optionalInt($fields, 'height'),
            MapValues::optionalInt($fields, 'millimeters_per_pixel'),
            MapValues::requireBool($fields, 'is_default'),
            MapValues::requireDateTime($fields, 'created_at'),
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

    /**
     * Смещение от глобальной эпохи.
     *
     * @return int|null Минуты или null.
     */
    public function getFromMinutes(): ?int
    {
        return $this->fromMinutes;
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

    /**
     * Подложка.
     *
     * @return int|null Id файла или null.
     */
    public function getBaseFileId(): ?int
    {
        return $this->baseFileId;
    }

    /**
     * Ширина.
     *
     * @return int|null Пиксели.
     */
    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * Высота.
     *
     * @return int|null Пиксели.
     */
    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * Масштаб.
     *
     * @return int|null Миллиметры на пиксель.
     */
    public function getMillimetersPerPixel(): ?int
    {
        return $this->millimetersPerPixel;
    }

    /**
     * Вариант по умолчанию.
     *
     * @return bool Флаг.
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Создан.
     *
     * @return DateTime Дата.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
