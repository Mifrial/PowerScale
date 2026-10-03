<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

/**
 * Spec способности. Ветка задаётся типом внутри документа.
 */
interface AbilitySpec extends RuleSpec
{
    /**
     * Блоки грантов. У группы их нет.
     *
     * @return array<int, AbilityGrantBlock> Блоки.
     */
    public function getGrantBlocks(): array;
}
