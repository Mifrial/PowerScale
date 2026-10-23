<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionRollFaceRemapEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionRollScoreAdjustEffect;

/**
 * Настройка броска попадания из карточки действия.
 */
final class GameRollAdjustment
{
    /**
     * Собирает ремап граней и дельты успехов карточки.
     *
     * Хвосты следующего действия сюда не входят: это отдельная задача.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка действия.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param int $hitNumber Номер попадания с единицы.
     *
     * @return RollAdjustment Настройка; пустая, если эффектов нет.
     */
    public function fromCard(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        string $profileType,
        int $hitNumber = 1,
    ): RollAdjustment {
        if ($actionRuleCode === '') {
            return new RollAdjustment();
        }

        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return new RollAdjustment();
        }

        $remap = [];
        $oneDelta = 0;
        $faceDelta = 0;
        foreach ($spec->getBase()->getActionEffects() as $effect) {
            if (
                $effect instanceof CurrentActionRollFaceRemapEffect
                && $effect->getScope()->appliesTo($profileType, $hitNumber)
            ) {
                $remap[$effect->getFrom()] = $effect->getTo();
                continue;
            }

            if (
                $effect instanceof CurrentActionRollScoreAdjustEffect
                && $effect->getScope()->appliesTo($profileType, $hitNumber)
            ) {
                $oneDelta += $effect->getOneDelta();
                $faceDelta += $effect->getFaceDelta();
            }
        }

        return new RollAdjustment($remap, $oneDelta, $faceDelta);
    }
}
