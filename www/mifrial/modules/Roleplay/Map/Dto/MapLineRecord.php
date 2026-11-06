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
     * @param string $color Цвет ленты.
     * @param int $speed Проходимость: 0 — непроходимо.
     * @param array<int, array{0: int, 1: int}> $points Осевая линия.
     * @param array<int, int> $widths Ширина в точках.
     * @param array<int, array{in: array{0: int, 1: int}, out: array{0: int, 1: int}}> $handles Ручки Безье.
     * @param array<int, array{speed: int, color: string|null}> $segments Стили сегментов; пусто — по умолчанию.
     * @param string $label Подпись.
     * @param bool $isPresent Лента есть в варианте.
     * @param int $sortOrder Порядок среди лент.
     *
     * @return void
     */
    private function __construct(
        private readonly int $id,
        private readonly int $variantId,
        private readonly string $color,
        private readonly int $speed,
        private readonly array $points,
        private readonly array $widths,
        private readonly array $handles,
        private readonly array $segments,
        private readonly string $label,
        private readonly bool $isPresent,
        private readonly int $sortOrder,
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
            MapValues::requireString($fields, 'color'),
            MapValues::requireInt($fields, 'speed'),
            $points,
            MapValues::widths($fields['widths'] ?? null, count($points)),
            MapValues::handles($fields['handles'] ?? null, count($points)),
            MapValues::segments($fields['segments'] ?? null, count($points) - 1),
            MapValues::requireString($fields, 'label'),
            MapValues::requireBool($fields, 'is_present'),
            MapValues::optionalInt($fields, 'sort_order') ?? 0,
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
     * Цвет.
     *
     * @return string Цвет.
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * Проходимость.
     *
     * @return int Скорость.
     */
    public function getSpeed(): int
    {
        return $this->speed;
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
     * Стили сегментов.
     *
     * @return array<int, array{speed: int, color: string|null}> Стили; пусто — по умолчанию.
     */
    public function getSegments(): array
    {
        return $this->segments;
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
     * Порядок среди лент.
     *
     * @return int Порядок.
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
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
