<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;

/**
 * Помехи защите прикрывающего из карточки прикрытия.
 */
final class GameStrikeCoverAdvantages
{
    /**
     * Помехи защите прикрывающего из его карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Карточка прикрытия.
     *
     * @return array<int, RollAdvantage> Помехи.
     */
    public function of(CharacterRuleSlice $slice, string $ruleCode): array
    {
        $rule = $slice->findLive($ruleCode);
        if ($rule === null || $rule->isSpecBroken()) {
            return [];
        }

        $cover = AbilityCombat::cover($rule->getSpecDocument());
        if (!$cover instanceof CoverAlly) {
            return [];
        }

        $rows = [];
        foreach ($cover->getDefenseAdvantages() as $entry) {
            $rows[] = new RollAdvantage(
                $entry->getSourceCode(),
                $entry->getDelta(),
                AdvantageSourceLabels::getLabel($entry->getSourceCode()),
            );
        }

        return $rows;
    }
}
