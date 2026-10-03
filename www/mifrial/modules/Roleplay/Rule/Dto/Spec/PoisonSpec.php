<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Spec яда.
 */
final class PoisonSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param ?string $iconCode Иконка.
     * @param string $damageTypeCode Тип урона.
     * @param ?DimensionalNumber $defaultStrength Сила по умолчанию.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $iconCode,
        private readonly string $damageTypeCode,
        private readonly ?DimensionalNumber $defaultStrength,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'poison';
    }

    /**
     * Иконка.
     *
     * @return ?string Значение.
     */
    public function getIconCode(): ?string
    {
        return $this->iconCode;
    }
    /**
     * Тип урона.
     *
     * @return string Значение.
     */
    public function getDamageTypeCode(): string
    {
        return $this->damageTypeCode;
    }
    /**
     * Сила по умолчанию.
     *
     * @return ?DimensionalNumber Значение.
     */
    public function getDefaultStrength(): ?DimensionalNumber
    {
        return $this->defaultStrength;
    }
}
