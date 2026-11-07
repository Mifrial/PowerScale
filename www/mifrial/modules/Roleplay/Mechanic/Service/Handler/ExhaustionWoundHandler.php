<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IExhaustionWound;

/**
 * Маркер exhaustion_to_wound. Подписок нет.
 */
final class ExhaustionWoundHandler extends MarkerMechanicHandler implements IExhaustionWound
{
    /**
     * Возвращает код exhaustion_to_wound.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'exhaustion_to_wound';
    }
}
