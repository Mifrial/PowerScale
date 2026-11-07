<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IExhaustionAmount;

/**
 * Маркер exhaustion_to_shock. Подписок нет.
 */
final class ExhaustionShockHandler extends MarkerMechanicHandler implements IExhaustionAmount
{
    /**
     * Возвращает код exhaustion_to_shock.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'exhaustion_to_shock';
    }
}
