<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Прочитанная строка `map_variant_node`.
 */
final class MapPlacementRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $variantId Вариант.
     * @param int $nodeId Узел.
     * @param int $x Координата X.
     * @param int $y Координата Y.
     * @param bool $isPresent Узел есть в варианте.
     * @param string $labelOverride Подпись в варианте.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $variantId,
        private readonly int $nodeId,
        private readonly int $x,
        private readonly int $y,
        private readonly bool $isPresent,
        private readonly string $labelOverride,
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
            MapValues::requireInt($fields, 'variant_id'),
            MapValues::requireInt($fields, 'node_id'),
            MapValues::requireInt($fields, 'x'),
            MapValues::requireInt($fields, 'y'),
            MapValues::requireBool($fields, 'is_present'),
            MapValues::requireString($fields, 'label_override'),
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
     * Вариант.
     *
     * @return int Id варианта.
     */
    public function getVariantId(): int
    {
        return $this->variantId;
    }

    /**
     * Узел.
     *
     * @return int Id узла.
     */
    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    /**
     * Координата X.
     *
     * @return int Пиксели.
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * Координата Y.
     *
     * @return int Пиксели.
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * Присутствует.
     *
     * @return bool Флаг.
     */
    public function isPresent(): bool
    {
        return $this->isPresent;
    }

    /**
     * Подпись варианта.
     *
     * @return string Подпись.
     */
    public function getLabelOverride(): string
    {
        return $this->labelOverride;
    }
}
