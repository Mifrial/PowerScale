<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Параметр способности.
 */
final class AbilityParameter
{
    /**
     * Создаёт параметр.
     *
     * @param string $code Код.
     * @param string $label Подпись.
     * @param string|null $description Описание.
     * @param string $resolution purchase или activation.
     * @param string $kind scalar или dimensional.
     * @param int|DimensionalNumber $default Значение по умолчанию.
     * @param int|DimensionalNumber|null $min Минимум.
     * @param int|DimensionalNumber|null $max Максимум.
     * @param ParameterLink|null $linked Связь с другим параметром.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly ?string $description,
        private readonly string $resolution,
        private readonly string $kind,
        private readonly int|DimensionalNumber $default,
        private readonly int|DimensionalNumber|null $min,
        private readonly int|DimensionalNumber|null $max,
        private readonly ?ParameterLink $linked,
    ) {
    }

    /**
     * Код.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Вид.
     *
     * @return string scalar или dimensional.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Значение по умолчанию.
     *
     * @return int|DimensionalNumber Значение.
     */
    public function getDefault(): int|DimensionalNumber
    {
        return $this->default;
    }

    /**
     * Связь с другим параметром.
     *
     * @return ParameterLink|null Связь или null.
     */
    public function getLinked(): ?ParameterLink
    {
        return $this->linked;
    }
}
