<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterInventoryChoice;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Spec\ItemModifierOperations;

/**
 * Надетый предмет после стека модификаторов.
 */
final class CharacterEquippedLive
{
    /**
     * Effective spec надетых строк.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<int, array{ruleCode: string, spec: ItemSpec}> Предметы.
     */
    public function equipped(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $rows = [];
        foreach ($choices->getInventory() as $item) {
            if (!$item->isEquipped()) {
                continue;
            }

            $spec = $this->effective($slice, $item);
            if ($spec !== null) {
                $rows[] = ['ruleCode' => $item->getRuleCode(), 'spec' => $spec];
            }
        }

        return $rows;
    }

    /**
     * Надетые предметы и врождённые, даже если флаг надетости снят.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<int, array{ruleCode: string, spec: ItemSpec}> Предметы.
     */
    public function equippedOrInnate(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $rows = [];
        foreach ($choices->getInventory() as $item) {
            $spec = $this->effective($slice, $item);
            if ($spec !== null && ($item->isEquipped() || $spec->isInnate())) {
                $rows[] = ['ruleCode' => $item->getRuleCode(), 'spec' => $spec];
            }
        }

        return $rows;
    }

    /**
     * Предмет после apply модификаторов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterInventoryChoice $item Строка.
     *
     * @return ItemSpec|null Spec или null.
     */
    private function effective(CharacterRuleSlice $slice, CharacterInventoryChoice $item): ?ItemSpec
    {
        $rule = $slice->findLive($item->getRuleCode());
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->isSpecBroken() || !$spec instanceof ItemSpec) {
            return null;
        }

        $modifiers = $this->modifiers($slice, $item->getModifiers());

        return ItemModifierOperations::apply($spec, $modifiers, $rule->getKeywordCodes());
    }

    /**
     * Живые spec модификаторов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, string> $codes Коды.
     *
     * @return array<int, ItemModifierSpec> Spec.
     */
    private function modifiers(CharacterRuleSlice $slice, array $codes): array
    {
        $specs = [];
        foreach ($codes as $code) {
            $rule = $slice->findLive($code);
            $spec = $rule?->getSpec();
            if ($rule !== null && !$rule->isSpecBroken() && $spec instanceof ItemModifierSpec) {
                $specs[] = $spec;
            }
        }

        return $specs;
    }
}
