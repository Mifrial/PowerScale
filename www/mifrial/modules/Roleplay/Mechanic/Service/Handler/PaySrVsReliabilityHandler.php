<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IReliabilityCut;

/**
 * Маркер pay_sr_vs_reliability. Подписок нет.
 */
final class PaySrVsReliabilityHandler extends MarkerMechanicHandler implements IReliabilityCut
{
    /**
     * Возвращает код pay_sr_vs_reliability.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'pay_sr_vs_reliability';
    }
}
