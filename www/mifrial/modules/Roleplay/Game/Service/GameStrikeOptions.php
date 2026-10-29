<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\OptionalAfterStrikeCheckEffect;

/**
 * Опции после удара: что персонаж реально может объявить вместе с карточкой.
 */
final class GameStrikeOptions
{
    /**
     * Все опции, купленные актором, по карточкам-родителям.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return list<array{actionCode: string, code: string, name: string, summary: string}> Опции.
     */
    public function available(CharacterRuleSlice $slice, array $abilityLevels): array
    {
        $rows = [];
        foreach ($this->resolved($slice, $abilityLevels) as $row) {
            $rows[] = [
                'actionCode' => $row['actionCode'],
                'code' => $row['code'],
                'name' => $row['name'],
                'summary' => $this->summary($slice, $row['effect']),
            ];
        }

        return $rows;
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
     * Опция карточки по коду.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param string $actionRuleCode Карточка действия.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     * @param string $code Код опции.
     *
     * @return array{code: string, name: string, effect: OptionalAfterStrikeCheckEffect} Опция.
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

        throw new GameInvalidException('Game strike after-strike option is invalid');
    }

    /**
     * Купленные дети с эффектом после удара.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param array<string, int> $abilityLevels Уровни способностей актора.
     *
     * @return list<array{actionCode: string, code: string, name: string, effect: OptionalAfterStrikeCheckEffect}>
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
            if (!$spec instanceof AbilityActionSpec && !$spec instanceof AbilityPlainSpec) {
                continue;
            }

            $base = $spec->getBase();
            $parent = $base->getParentAbilityCode();
            if ($parent === null || $parent === '') {
                continue;
            }

            foreach ($base->getActionEffects() as $effect) {
                if (!$effect instanceof OptionalAfterStrikeCheckEffect) {
                    continue;
                }

                $rows[] = [
                    'actionCode' => $parent,
                    'code' => $rule->getCode(),
                    'name' => $rule->getName(),
                    'effect' => $effect,
                ];
            }
        }

        return $rows;
    }

    /**
     * Человеческая подпись опции.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param OptionalAfterStrikeCheckEffect $effect Эффект.
     *
     * @return string Текст.
     */
    private function summary(CharacterRuleSlice $slice, OptionalAfterStrikeCheckEffect $effect): string
    {
        $check = $slice->findLive($effect->getCheckCode())?->getName() ?? $effect->getCheckCode();
        $damage = $slice->findLive($effect->getSelfDamage()->getDamageTypeCode())?->getName()
            ?? $effect->getSelfDamage()->getDamageTypeCode();
        $internal = $effect->getSelfDamage()->isInternal() ? 'внутренний ' : '';
        $skip = $effect->isSkipParentPending() ? ', помеха действия снимается' : '';
        $delta = $effect->getSelfDamage()->getSizeDelta();
        $shift = $delta === 0
            ? ''
            : sprintf(' ([сила удара]%s%d)', $delta < 0 ? '↓' : '↑', abs($delta));

        return sprintf(
            '%s против %d: успех — %s%s урон по себе%s%s.',
            $check,
            $effect->getDifficulty(),
            $internal,
            mb_strtolower($damage),
            $shift,
            $skip,
        );
    }
}
