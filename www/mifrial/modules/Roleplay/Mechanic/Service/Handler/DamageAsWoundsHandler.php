<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IDamageAsWounds;

/**
 * Маркер damage_as_wounds_by_size. Подписок нет.
 */
final class DamageAsWoundsHandler extends MarkerMechanicHandler implements IDamageAsWounds
{
    /**
     * Возвращает код damage_as_wounds_by_size.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'damage_as_wounds_by_size';
    }
}
