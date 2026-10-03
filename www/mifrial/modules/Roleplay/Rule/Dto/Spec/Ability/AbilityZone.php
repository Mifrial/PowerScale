<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Цена зоны способности.
 */
interface AbilityZone
{
    /**
     * Вид цены.
     *
     * @return string Код.
     */
    public function getType(): string;
}
