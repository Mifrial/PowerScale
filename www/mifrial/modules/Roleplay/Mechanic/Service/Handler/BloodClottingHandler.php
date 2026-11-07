<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IBloodClotting;

/**
 * Маркер blood_clotting. Подписок нет.
 */
final class BloodClottingHandler extends MarkerMechanicHandler implements IBloodClotting
{
    /**
     * Возвращает код blood_clotting.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'blood_clotting';
    }
}
