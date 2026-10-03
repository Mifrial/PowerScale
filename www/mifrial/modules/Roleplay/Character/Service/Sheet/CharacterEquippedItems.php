<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterCharacteristicLimit;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterEquippedModifier;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\FixedNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ArmorBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\CharacteristicLimit;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ShieldBlock;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Снимок надетого предмета. Слоты и руки не проверяет.
 */
final class CharacterEquippedItems
{
    /**
     * Поля надетых item.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return array<int, CharacterEquippedModifier> Снимки.
     */
    public function getModifiers(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): array
    {
        $modifiers = [];
        foreach ($choices->getInventory() as $index => $item) {
            if (!$item->isEquipped()) {
                continue;
            }

            $modifier = $this->modifierOf($slice, $item->getRuleCode(), $index, $problems);
            if ($modifier !== null) {
                $modifiers[] = $modifier;
            }
        }

        return $modifiers;
    }

    /**
     * Один надетый предмет.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     * @param int $index Индекс инвентаря.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return CharacterEquippedModifier|null Снимок или null.
     */
    private function modifierOf(
        CharacterRuleSlice $slice,
        string $ruleCode,
        int $index,
        CharacterProblemList $problems,
    ): ?CharacterEquippedModifier {
        $spec = $this->itemSpec($slice, $ruleCode);
        if ($spec === null) {
            $problems->add('CHARACTER_EQUIPPED', 'Equipped rule is not a live item', 'inventory', 'inventory.' . $index);

            return null;
        }

        $armor = $spec->getArmor();

        return new CharacterEquippedModifier(
            $ruleCode,
            $armor?->getStrengthPenalty(),
            $this->agility($armor?->getMaxAgility()),
            array_merge($this->limitsOf($armor), $this->limitsOf($spec->getShield())),
        );
    }

    /**
     * Живой предмет.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код.
     *
     * @return ItemSpec|null Spec или null.
     */
    private function itemSpec(CharacterRuleSlice $slice, string $ruleCode): ?ItemSpec
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof ItemSpec ? $spec : null;
    }

    /**
     * База размерного потолка.
     *
     * @param DimensionalNumber|null $agility Потолок.
     *
     * @return int|null Число или null.
     */
    private function agility(?DimensionalNumber $agility): ?int
    {
        return $agility?->getBase();
    }

    /**
     * Лимиты брони или щита.
     *
     * @param ArmorBlock|ShieldBlock|null $part Блок.
     *
     * @return array<int, CharacterCharacteristicLimit> Лимиты.
     */
    private function limitsOf(ArmorBlock|ShieldBlock|null $part): array
    {
        if ($part === null) {
            return [];
        }

        $limits = [];
        foreach ($part->getCharacteristicLimits() as $limit) {
            $row = $this->limitOf($limit);
            if ($row !== null) {
                $limits[] = $row;
            }
        }

        return $limits;
    }

    /**
     * Одна строка лимита. Формула характеристики в число листа не входит.
     *
     * @param CharacteristicLimit $limit Лимит.
     *
     * @return CharacterCharacteristicLimit|null Потолок или null.
     */
    private function limitOf(CharacteristicLimit $limit): ?CharacterCharacteristicLimit
    {
        $formula = $limit->getLimit();
        if ($formula instanceof FixedNode) {
            return new CharacterCharacteristicLimit($limit->getCharacteristicCode(), $formula->getValue());
        }

        if ($formula instanceof DimensionalNode) {
            return new CharacterCharacteristicLimit($limit->getCharacteristicCode(), $formula->getNumber()->getBase());
        }

        return null;
    }
}
