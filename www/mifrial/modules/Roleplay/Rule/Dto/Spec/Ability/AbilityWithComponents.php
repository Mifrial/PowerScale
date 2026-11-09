<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ActionComponent;

/**
 * Spec способности с общими компонентами действия.
 */
interface AbilityWithComponents extends AbilityWithBase
{
    /**
     * Компоненты действия.
     *
     * @return array<int, ActionComponent> Компоненты.
     */
    public function getComponents(): array;
}
