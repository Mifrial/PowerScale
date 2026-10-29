<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Не вешать ожидание после действия.
 */
final class SkipAfterActionOutcomeEffect implements ModifierOutcomeEffect
{
    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'skip_after_action';
    }
}
