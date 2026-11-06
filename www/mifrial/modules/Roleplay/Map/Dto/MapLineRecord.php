<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Dto;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Прочитанная строка `map_variant_line`: лента реки или дороги.
 */
final class MapLineRecord
{
    /**
     * Собирает запись.
     *
     * @param int $id Id.
     * @param int $variantId Вариант.
     * @param int $terrainId Террейн.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, int> $widths Ширина в точках.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param string $label Подпись.
     * @param bool $isPresent Лента есть в варианте.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $variantId,
        private readonly int $terrainId,
        private readonly array $points,
        private readonly array $widths,
        private readonly array $handles,
        private readonly string $label,
        private readonly bool $isPresent,
    ) {
    }

    /**
     * Строка из гидрата SmartTable.
     *
     * @param array<string, mixed> $fields Строка.
     *
     * @return self Запись.
     *
     * @throws MapInvalidException Если геометрия битая.
     */
    public static function fromNormalized(array $fields): self
    {
        $points = MapValues::path($fields, 'points', 2, true);
        if ($points === null) {
            throw new MapInvalidException('Map line has no points');
        }

        return new self(
            MapValues::requireInt($fields, 'id'),
            MapValues::requireInt($fields, 'variant_id'),
            MapValues::requireInt($fields, 'terrain_id'),
            $points,
            MapValues::widths($fields['widths'] ?? null, count($points)),
            MapValues::handles($fields['handles'] ?? null, count($points)),
            MapValues::requireString($fields, 'label'),
            MapValues::requireBool($fields, 'is_present'),
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
     * Террейн.
     *
     * @return int Id террейна.
     */
    public function getTerrainId(): int
    {
        return $this->terrainId;
    }

    /**
     * Осевая линия.
     *
     * @return array<int, array{0: int, 1: int}> Точки.
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * Ширина в точках.
     *
     * @return array<int, int> Пиксели.
     */
    public function getWidths(): array
    {
        return $this->widths;
    }

    /**
     * Ручки Безье.
     *
     * @return array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> Ручки.
     */
    public function getHandles(): array
    {
        return $this->handles;
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
     * Присутствует.
     *
     * @return bool Флаг.
     */
    public function isPresent(): bool
    {
        return $this->isPresent;
    }
}
