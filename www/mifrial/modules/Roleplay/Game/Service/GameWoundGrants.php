<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ActionResourceCutGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\WoundBandageAidGrant;

/**
 * Гранты купленных навыков для цены и перевязки раны.
 */
final class GameWoundGrants
{
    /**
     * Гранты блоков не выше купленного уровня.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<int, object> Гранты.
     */
    public function grants(CharacterRuleSlice $slice, array $choices): array
    {
        $abilities = $choices['abilities'] ?? [];
        if (!is_array($abilities)) {
            return [];
        }

        $grants = [];
        foreach ($abilities as $choice) {
            array_push($grants, ...$this->choiceGrants($slice, $choice));
        }

        return $grants;
    }



    /**
     * Есть грант снятия потолка.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return bool true, если грант куплен.
     */
    public function hasAid(CharacterRuleSlice $slice, array $choices): bool
    {
        foreach ($this->grants($slice, $choices) as $grant) {
            if ($grant instanceof WoundBandageAidGrant) {
                return true;
            }
        }

        return false;
    }

    /**
     * Вычитает безусловные срезы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     * @param string $actionRuleCode Карточка.
     * @param array<string, int> $amounts Цены.
     *
     * @return array<string, bool> Затронутые ресурсы.
     */
    public function cuts(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        array &$amounts,
    ): array {
        $touched = [];
        foreach ($this->grants($slice, $choices) as $grant) {
            if (!$grant instanceof ActionResourceCutGrant || $grant->getActionRuleCode() !== $actionRuleCode) {
                continue;
            }

            $code = $grant->getResourceCode();
            if (!array_key_exists($code, $amounts)) {
                continue;
            }

            $amounts[$code] -= $grant->getAmount();
            $touched[$code] = true;
        }

        return $touched;
    }
    /**
     * Гранты одной купленной способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $choice Запись способности.
     *
     * @return array<int, object> Гранты.
     */
    private function choiceGrants(CharacterRuleSlice $slice, mixed $choice): array
    {
        $code = is_array($choice) ? ($choice['ruleCode'] ?? null) : null;
        $level = is_array($choice) ? ($choice['level'] ?? null) : null;
        $rule = is_string($code) ? $slice->findLive($code) : null;
        if (!$rule instanceof CharacterResolvedRule || !is_int($level) || !$rule->getSpec() instanceof AbilitySpec) {
            return [];
        }

        $grants = [];
        foreach ((new CharacterDonorGrants())->getForLevel($rule, $code, $level) as $donor) {
            $grants[] = $donor->getGrant();
        }

        return $grants;
    }
}
