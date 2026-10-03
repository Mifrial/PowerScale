<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit;

/**
 * Попадания нет.
 */
final class NoneHit implements HitResolution
{
    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'none';
    }
}
