<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;

/**
 * Зона без цены: способность приходит сама.
 */
final class AutomaticZone implements AbilityZone
{
    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'automatic';
    }
}
