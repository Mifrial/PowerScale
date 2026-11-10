<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Снимок локации для JSON; поля — данные, не порты.

namespace Mifrial\Roleplay\Scene\Dto;

/**
 * Полный снимок локации для просмотра и редактора.
 */
final class SceneDetail
{
    /**
     * Собирает снимок.
     *
     * @param SceneRecord $sceneRecord Локация.
     * @param array<int, SceneBoardRecord> $boards Поля.
     * @param array<int, SceneElementRecord> $elements Элементы.
     * @param array<int, SceneEnclosureRecord> $enclosures Вложения.
     * @param bool $canEdit Можно править.
     *
     * @return void
     */
    public function __construct(
        private readonly SceneRecord $sceneRecord,
        private readonly array $boards,
        private readonly array $elements,
        private readonly array $enclosures,
        private readonly bool $canEdit,
    ) {
    }

    /**
     * Локация.
     *
     * @return SceneRecord Локация.
     */
    public function getScene(): SceneRecord
    {
        return $this->sceneRecord;
    }

    /**
     * Поля.
     *
     * @return array<int, SceneBoardRecord> Поля.
     */
    public function getBoards(): array
    {
        return $this->boards;
    }

    /**
     * Элементы.
     *
     * @return array<int, SceneElementRecord> Элементы.
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * Вложения.
     *
     * @return array<int, SceneEnclosureRecord> Вложения.
     */
    public function getEnclosures(): array
    {
        return $this->enclosures;
    }

    /**
     * Можно править.
     *
     * @return bool Флаг.
     */
    public function canEdit(): bool
    {
        return $this->canEdit;
    }
}
