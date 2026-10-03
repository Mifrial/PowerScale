<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\State;

/**
 * Затухание урона состояния.
 */
interface StateDecay
{
    /**
     * Вид затухания.
     *
     * @return string Код.
     */
    public function getKind(): string;
}
