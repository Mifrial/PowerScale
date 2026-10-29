<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;

/**
 * Модификаторы действия: что персонаж реально может объявить вместе с карточкой.
 */
final class GameActionModifiers
{
    /**
     * Все модификаторы, купленные актором, по карточкам-родителям.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return list<array{
     *     actionCode: string,
     *     actionName: string,
     *     code: string,
     *     name: string,
     *     effect: ActionModifierEffect,
     * }>
     */
    public function available(CharacterRuleSlice $slice, array $abilityLevels): array
    {
        return $this->resolved($slice, $abilityLevels);
    }

    /**
     * Требует, чтобы каждый код был доступен карточке актора.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param string $actionRuleCode Карточка действия.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     * @param array<int, string> $codes Выбранные коды.
     *
     * @return void
     *
     * @throws GameInvalidException Если код чужой карточке или не куплен.
     */
    public function assertAvailable(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $abilityLevels,
        array $codes,
    ): void {
        foreach ($codes as $code) {
            $this->find($slice, $actionRuleCode, $abilityLevels, $code);
        }
    }

    /**
     * Модификатор карточки по коду.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param string $actionRuleCode Карточка действия.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     * @param string $code Код модификатора.
     *
     * @return array{code: string, name: string, effect: ActionModifierEffect} Модификатор.
     *
     * @throws GameInvalidException Если код чужой карточке или не куплен.
     */
    public function find(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $abilityLevels,
        string $code,
    ): array {
        foreach ($this->resolved($slice, $abilityLevels) as $row) {
            if ($row['actionCode'] === $actionRuleCode && $row['code'] === $code) {
                return [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'effect' => $row['effect'],
                ];
            }
        }

        throw new GameInvalidException('Game action modifier is invalid');
    }

    /**
     * Купленные дети с модификатором действия.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return list<array{
     *     actionCode: string,
     *     actionName: string,
     *     code: string,
     *     name: string,
     *     effect: ActionModifierEffect,
     * }>
     */
    private function resolved(CharacterRuleSlice $slice, array $abilityLevels): array
    {
        $rows = [];
        foreach ($slice->getLiveRules() as $rule) {
            $level = $abilityLevels[$rule->getCode()] ?? 0;
            if (!is_int($level) || $level < 1) {
                continue;
            }

            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof AbilityWithBase) {
                continue;
            }

            $base = $spec->getBase();
            $parent = $base->getParentAbilityCode();
            if ($parent === null || $parent === '') {
                continue;
            }

            foreach ($base->getActionEffects() as $effect) {
                if (!$effect instanceof ActionModifierEffect) {
                    continue;
                }

                $rows[] = [
                    'actionCode' => $parent,
                    'actionName' => $slice->findLive($parent)?->getName() ?? $parent,
                    'code' => $rule->getCode(),
                    'name' => $rule->getName(),
                    'effect' => $effect,
                ];
            }
        }

        return $rows;
    }
}
