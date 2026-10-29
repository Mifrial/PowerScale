<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Моменты, в которые движок умеет исполнять модификатор действия.
 */
final class ModifierTrigger
{
    /**
     * После закрытия удара, независимо от попадания.
     */
    public const AFTER_STRIKE = 'after_strike';

    /**
     * Поддерживает ли движок момент.
     *
     * @param string $value Значение из спеки.
     *
     * @return bool true, если поддерживает.
     */
    public static function supports(string $value): bool
    {
        return $value === self::AFTER_STRIKE;
    }
}
