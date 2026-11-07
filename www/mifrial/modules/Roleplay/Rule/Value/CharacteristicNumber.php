<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Размерное число шкалы характеристик. База 3–5, шаг размера 3.
 */
final class CharacteristicNumber extends DimensionalNumber
{
    public const BASE_MIN = 3;

    public const BASE_MAX = 5;

    public const BASE_STEP = 3;

    /**
     * Создаёт число.
     *
     * @param int $base База.
     * @param int $size Размер.
     * @param int|null $baseMin Нижняя граница базы или null.
     * @param int|null $baseMax Верхняя граница базы или null.
     *
     * @return void
     */
    public function __construct(
        int $base,
        int $size,
        ?int $baseMin = null,
        ?int $baseMax = null,
    ) {
        parent::__construct($base, $size, $baseMin ?? self::BASE_MIN, $baseMax ?? self::BASE_MAX);
    }

    /**
     * Сравнивает значения по шкале характеристик: base + 3×size.
     *
     * @param self $other Второе число.
     *
     * @return int -1, 0 или 1.
     */
    public function characteristicCompare(self $other): int
    {
        return ($this->getBase() + self::BASE_STEP * $this->getSize())
            <=> ($other->getBase() + self::BASE_STEP * $other->getSize());
    }

    /**
     * У характеристик размер меняется только через modify.
     *
     * @param int $targetSize Целевой размер.
     *
     * @return never Никогда.
     *
     * @throws RuleInvalidException Всегда.
     */
    public function withSize(int $targetSize): never
    {
        throw new RuleInvalidException('Для шкалы характеристик используйте modify, а не размер ' . $targetSize);
    }

    /**
     * Копия с новой базой и размером.
     *
     * @param int $base База.
     * @param int $size Размер.
     *
     * @return static Копия.
     */
    protected function copyWithBaseSize(int $base, int $size): static
    {
        return new static($base, $size);
    }
}
