<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IInjuryExtraDice;

/**
 * Маркер injury_extra_dice_from_sr. Подписок нет.
 */
final class InjuryExtraDiceHandler extends MarkerMechanicHandler implements IInjuryExtraDice
{
    /**
     * Возвращает код injury_extra_dice_from_sr.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'injury_extra_dice_from_sr';
    }
}
