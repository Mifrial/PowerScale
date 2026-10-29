<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Spec способности с общими полями и родителем.
 */
interface AbilityWithBase extends AbilitySpec
{
    /**
     * Общие поля способности.
     *
     * @return AbilityBase Значение.
     */
    public function getBase(): AbilityBase;
}
