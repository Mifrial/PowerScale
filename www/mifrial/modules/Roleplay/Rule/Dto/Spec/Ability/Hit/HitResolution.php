<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit;

/**
 * Доставка попадания.
 */
interface HitResolution
{
    /**
     * Вид доставки.
     *
     * @return string Код.
     */
    public function getType(): string;
}
