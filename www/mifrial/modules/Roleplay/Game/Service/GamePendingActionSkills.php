<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionCostEffect;

/**
 * Хвосты next_action_cost купленных навыков.
 */
final class GamePendingActionSkills
{
    /**
     * Создаёт каталог навыков.
     *
     * @param GamePendingActionTails $tails Карточные хвосты.
     *
     * @return void
     */
    public function __construct(private readonly GamePendingActionTails $tails)
    {
    }

    /**
     * Эффекты купленных навыков, чей фильтр совпал с этой командой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<int, array<string, mixed>> Список.
     */
    public function queued(CharacterRuleSlice $slice, array $choices, string $actionRuleCode): array
    {
        $queued = [];
        $abilities = $choices['abilities'] ?? [];
        if (!is_array($abilities)) {
            return [];
        }

        foreach ($abilities as $choice) {
            $code = is_array($choice) ? ($choice['ruleCode'] ?? null) : null;
            $level = is_array($choice) ? ($choice['level'] ?? null) : null;
            if (!is_string($code) || !is_int($level) || $level < 1) {
                continue;
            }

            array_push($queued, ...$this->skillEffects($slice, $code, $actionRuleCode));
        }

        return $queued;
    }

    /**
     * Фильтр эффекта совпал с карточкой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $effect Эффект.
     *
     * @return bool true, если совпал.
     */
    public function matches(CharacterRuleSlice $slice, string $actionRuleCode, array $effect): bool
    {
        $keywords = $effect['keywords'] ?? null;
        if (!is_array($keywords) || $keywords === []) {
            return ($effect['ruleCode'] ?? null) === $actionRuleCode;
        }

        $rule = $slice->findLive($actionRuleCode);

        return $rule !== null && array_diff($keywords, $rule->getKeywordCodes()) === [];
    }

    /**
     * next_action_cost одной способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $skillCode Навык.
     * @param string $actionRuleCode Карточка команды.
     *
     * @return array<int, array<string, mixed>> Строки карты.
     */
    private function skillEffects(CharacterRuleSlice $slice, string $skillCode, string $actionRuleCode): array
    {
        $base = $this->tails->abilityBase($slice, $skillCode);
        if ($base === null) {
            return [];
        }

        $rows = [];
        foreach ($base->getActionEffects() as $effect) {
            $stored = $this->stored($effect);
            if ($stored !== null && $this->matches($slice, $actionRuleCode, $stored)) {
                $rows[] = ['sourceRuleCode' => $skillCode, 'effect' => $stored];
            }
        }

        return $rows;
    }

    /**
     * Сохраняемая форма next_action_cost.
     *
     * @param mixed $effect Эффект карточки.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    private function stored(mixed $effect): ?array
    {
        if (!$effect instanceof NextActionCostEffect) {
            return null;
        }

        $stored = [
            'type' => 'next_action_cost',
            'resource_code' => $effect->getResourceCode(),
            'delta' => $effect->getDelta(),
        ];
        if ($effect->getRuleCode() !== null) {
            $stored['ruleCode'] = $effect->getRuleCode();
        }

        if ($effect->getKeywords() !== []) {
            $stored['keywords'] = $effect->getKeywords();
        }

        return $stored;
    }
}
