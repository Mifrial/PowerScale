<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Прочитанная строка `scene_element`.
 */
final class SceneElementRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $boardId Поле.
     * @param string $kind Тип элемента.
     * @param array<string|int, mixed> $shape Геометрия.
     * @param array<string|int, mixed> $props Свойства типа.
     * @param int $sortOrder Порядок.
     * @param int|null $targetBoardId Целевое поле проёма.
     * @param int|null $assetFileId Ассет декора.
     * @param DateTime $createdAt Создан.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $boardId,
        private readonly string $kind,
        private readonly array $shape,
        private readonly array $props,
        private readonly int $sortOrder,
        private readonly ?int $targetBoardId,
        private readonly ?int $assetFileId,
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
            SceneValues::requireInt($fields, 'board_id'),
            SceneValues::requireString($fields, 'kind'),
            SceneValues::requireArray($fields, 'shape'),
            SceneValues::optionalArray($fields, 'props'),
            SceneValues::requireInt($fields, 'sort_order'),
            SceneValues::optionalInt($fields, 'target_board_id'),
            SceneValues::optionalInt($fields, 'asset_file_id'),
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
     * Поле.
     *
     * @return int Id поля.
     */
    public function getBoardId(): int
    {
        return $this->boardId;
    }

    /**
     * Тип.
     *
     * @return string kind.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Геометрия.
     *
     * @return array<string|int, mixed> shape.
     */
    public function getShape(): array
    {
        return $this->shape;
    }

    /**
     * Свойства.
     *
     * @return array<string|int, mixed> props.
     */
    public function getProps(): array
    {
        return $this->props;
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
     * Целевое поле.
     *
     * @return int|null Id поля или null.
     */
    public function getTargetBoardId(): ?int
    {
        return $this->targetBoardId;
    }

    /**
     * Ассет.
     *
     * @return int|null Id файла или null.
     */
    public function getAssetFileId(): ?int
    {
        return $this->assetFileId;
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
