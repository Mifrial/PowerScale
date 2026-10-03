<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit;

/**
 * Попадание атакой.
 */
final class AttackHit implements HitResolution
{
    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'attack';
    }
}
