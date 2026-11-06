<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionCheckModifierEffect;

/**
 * Проверочное преимущество эффекта карточки.
 */
final class GameStrikeCheckAdvantage
{
    /**
     * Строит запись для подходящего эффекта.
     *
     * @param mixed $effect Эффект карточки.
     * @param string $checkCode Код проверки.
     *
     * @return RollAdvantage|null Запись или null.
     */
    public static function of(mixed $effect, string $checkCode): ?RollAdvantage
    {
        if (!$effect instanceof CurrentActionCheckModifierEffect) {
            return null;
        }

        if (!in_array($checkCode, $effect->getCheckCodes(), true)) {
            return null;
        }

        $source = $effect->getSourceCode();
        $source = is_string($source) && $source !== '' ? $source : null;

        return new RollAdvantage($source, $effect->getDelta(), AdvantageSourceLabels::getLabel($source));
    }
}
