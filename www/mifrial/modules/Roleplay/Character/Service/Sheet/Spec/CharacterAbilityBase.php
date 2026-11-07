<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;

/**
 * Общая база способности из любого поддерживаемого вида spec.
 */
final class CharacterAbilityBase
{
    /**
     * База способности или null для чужого вида.
     *
     * @param mixed $spec Спека.
     *
     * @return AbilityBase|null База или null.
     */
    public static function of(mixed $spec): ?AbilityBase
    {
        if (
            $spec instanceof AbilityActionSpec
            || $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityProcessSpec
            || $spec instanceof AbilitySpellSpec
        ) {
            return $spec->getBase();
        }

        return null;
    }
}
