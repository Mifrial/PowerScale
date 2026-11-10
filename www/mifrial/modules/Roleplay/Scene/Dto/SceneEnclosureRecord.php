<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `scene_enclosure`.
 */
final class SceneEnclosureRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $parentBoardId Родительское поле.
     * @param int $childBoardId Вложенное поле.
     * @param array<string|int, mixed> $footprint Контур на родителе.
     * @param int $sortOrder Порядок.
     * @param DateTime $createdAt Создано.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $parentBoardId,
        private readonly int $childBoardId,
        private readonly array $footprint,
        private readonly int $sortOrder,
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
            SceneValues::requireInt($fields, 'parent_board_id'),
            SceneValues::requireInt($fields, 'child_board_id'),
            SceneValues::requireArray($fields, 'footprint'),
            SceneValues::requireInt($fields, 'sort_order'),
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
     * Родительское поле.
     *
     * @return int Id поля.
     */
    public function getParentBoardId(): int
    {
        return $this->parentBoardId;
    }

    /**
     * Вложенное поле.
     *
     * @return int Id поля.
     */
    public function getChildBoardId(): int
    {
        return $this->childBoardId;
    }

    /**
     * Контур.
     *
     * @return array<string|int, mixed> shape.
     */
    public function getFootprint(): array
    {
        return $this->footprint;
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
     * Создано.
     *
     * @return DateTime Дата.
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
