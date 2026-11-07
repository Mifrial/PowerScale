<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IBluntUnconscious;

/**
 * Маркер blunt_unconscious. Подписок нет.
 */
final class BluntUnconsciousHandler extends MarkerMechanicHandler implements IBluntUnconscious
{
    /**
     * Возвращает код blunt_unconscious.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'blunt_unconscious';
    }
}
