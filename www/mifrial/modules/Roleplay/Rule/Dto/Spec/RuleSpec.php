<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Контракт spec одного типа правила.
 */
interface RuleSpec
{
    /**
     * Тип правила, которому принадлежит документ.
     *
     * @return string Код типа.
     */
    public function getRuleType(): string;
}
