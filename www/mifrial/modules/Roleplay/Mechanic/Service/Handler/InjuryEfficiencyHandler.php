<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IInjuryEfficiency;

/**
 * Маркер injury_efficiency. Подписок нет.
 */
final class InjuryEfficiencyHandler extends MarkerMechanicHandler implements IInjuryEfficiency
{
    /**
     * Возвращает код injury_efficiency.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'injury_efficiency';
    }
}
