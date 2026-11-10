<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `scene_board`.
 */
final class SceneBoardRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $locationId Локация.
     * @param string $name Имя.
     * @param int $sortOrder Порядок.
     * @param int|null $baseFileId Подложка.
     * @param int|null $baseFileWidth Ширина подложки в пикселях.
     * @param int|null $baseFileHeight Высота подложки в пикселях.
     * @param int $boundWidth Логическая ширина в единицах сцены.
     * @param int $boundHeight Логическая высота в единицах сцены.
     * @param int $occupancyCell Размер клетки occupancy.
     * @param int $zQuantum Квант высоты.
     * @param int $gridStep Шаг визуальной сетки.
     * @param DateTime $createdAt Создано.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $locationId,
        private readonly string $name,
        private readonly int $sortOrder,
        private readonly ?int $baseFileId,
        private readonly ?int $baseFileWidth,
        private readonly ?int $baseFileHeight,
        private readonly int $boundWidth,
        private readonly int $boundHeight,
        private readonly int $occupancyCell,
        private readonly int $zQuantum,
        private readonly int $gridStep,
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
            SceneValues::requireInt($fields, 'id'),
            SceneValues::requireInt($fields, 'location_id'),
            SceneValues::requireString($fields, 'name'),
            SceneValues::requireInt($fields, 'sort_order'),
            SceneValues::optionalInt($fields, 'base_file_id'),
            SceneValues::optionalInt($fields, 'base_file_width'),
            SceneValues::optionalInt($fields, 'base_file_height'),
            SceneValues::requireInt($fields, 'bound_width'),
            SceneValues::requireInt($fields, 'bound_height'),
            SceneValues::requireInt($fields, 'occupancy_cell'),
            SceneValues::requireInt($fields, 'z_quantum'),
            SceneValues::requireInt($fields, 'grid_step'),
            SceneValues::requireDateTime($fields, 'created_at'),
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
     * Локация.
     *
     * @return int Id локации.
     */
    public function getLocationId(): int
    {
        return $this->locationId;
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
     * Ширина подложки.
     *
     * @return int|null Пиксели или null.
     */
    public function getBaseFileWidth(): ?int
    {
        return $this->baseFileWidth;
    }

    /**
     * Высота подложки.
     *
     * @return int|null Пиксели или null.
     */
    public function getBaseFileHeight(): ?int
    {
        return $this->baseFileHeight;
    }

    /**
     * Логическая ширина.
     *
     * @return int Единицы сцены.
     */
    public function getBoundWidth(): int
    {
        return $this->boundWidth;
    }

    /**
     * Логическая высота.
     *
     * @return int Единицы сцены.
     */
    public function getBoundHeight(): int
    {
        return $this->boundHeight;
    }

    /**
     * Клетка occupancy.
     *
     * @return int Единицы сцены.
     */
    public function getOccupancyCell(): int
    {
        return $this->occupancyCell;
    }

    /**
     * Квант высоты.
     *
     * @return int Единицы сцены.
     */
    public function getZQuantum(): int
    {
        return $this->zQuantum;
    }

    /**
     * Шаг сетки.
     *
     * @return int Единицы сцены; 0 — выключена.
     */
    public function getGridStep(): int
    {
        return $this->gridStep;
    }

    /**
     * Создано.
     *
     * @return DateTime Дата.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
