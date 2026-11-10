<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

/**
 * Маркер «Магическое отклонение»: данные читает Game из payload, подписок нет.
 */
final class SpellDeviationHandler extends MarkerMechanicHandler
{
    /**
     * Код семейства.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'spell_deviation';
    }
}
