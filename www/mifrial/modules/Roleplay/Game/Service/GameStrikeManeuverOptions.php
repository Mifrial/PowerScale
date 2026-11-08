<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuverEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuverEffectSelector;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Подбор усиления объявленного манёвра по купленным карточкам актора.
 */
final class GameStrikeManeuverOptions
{
    /**
     * Купленное усиление, применимое к манёвру.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param array<string, int> $levels Уровни способностей актора.
     * @param string $maneuverCode Код объявленного манёвра.
     * @param DimensionalNumber|null $mastery Мастерство оружия прошлого удара.
     *
     * @return array{
     *     ruleCode: string,
     *     minSuccess: int,
     *     replaces: list<array{kind: string, state: string}>,
     *     effects: list<array{kind: string, state: string, amount: int}>
     * }|null Усиление или null.
     */
    public function boostOf(
        CharacterRuleSlice $slice,
        array $levels,
        string $maneuverCode,
        ?DimensionalNumber $mastery,
    ): ?array {
        $best = null;
        foreach ($slice->getLiveRules() as $rule) {
            if (($levels[$rule->getCode()] ?? 0) < 1) {
                continue;
            }

            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof AbilityWithBase) {
                continue;
            }

            $boost = $spec->getBase()->getManeuverBoost();
            if ($boost === null || !in_array($maneuverCode, $boost->getManeuvers(), true)) {
                continue;
            }

            if (!$this->masteryPasses($mastery, $boost->getMinWeaponMastery())) {
                continue;
            }

            $effects = self::effectsOf($boost->getEffects());
            if ($effects === []) {
                continue;
            }

            $candidate = [
                'ruleCode' => $rule->getCode(),
                'minSuccess' => $boost->getMinSuccess(),
                'replaces' => self::replacesOf($boost->getReplaces()),
                'effects' => $effects,
            ];
            if ($best === null || $this->better($candidate, $best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Мастерство оружия прошлого удара не ниже требования.
     *
     * @param DimensionalNumber|null $mastery Мастерство или null.
     * @param int|null $required Минимум или null.
     *
     * @return bool Истина, если требования нет или оно выполнено.
     */
    private function masteryPasses(?DimensionalNumber $mastery, ?int $required): bool
    {
        if ($required === null) {
            return true;
        }

        return $mastery !== null && $mastery->compare(new DimensionalNumber($required, 0)) >= 0;
    }

    /**
     * Кандидат сильнее текущего: выше порог, затем стабильный порядок по коду.
     *
     * @param array{
     *     ruleCode: string,
     *     minSuccess: int,
     *     replaces: list<array{kind: string, state: string}>,
     *     effects: list<array{kind: string, state: string, amount: int}>
     * } $candidate Кандидат.
     * @param array{
     *     ruleCode: string,
     *     minSuccess: int,
     *     replaces: list<array{kind: string, state: string}>,
     *     effects: list<array{kind: string, state: string, amount: int}>
     * } $best Текущий.
     *
     * @return bool Истина, если кандидат лучше.
     */
    private function better(array $candidate, array $best): bool
    {
        if ($candidate['minSuccess'] !== $best['minSuccess']) {
            return $candidate['minSuccess'] > $best['minSuccess'];
        }

        return strcmp($candidate['ruleCode'], $best['ruleCode']) < 0;
    }

    /**
     * Эффекты спеки в снимок строки.
     *
     * @param array<int, StrikeManeuverEffect> $effects Эффекты.
     *
     * @return list<array{kind: string, state: string, amount: int}> Снимок.
     */
    public static function effectsOf(array $effects): array
    {
        $rows = [];
        foreach ($effects as $effect) {
            $rows[] = [
                'kind' => $effect->getKind(),
                'state' => $effect->getState(),
                'amount' => $effect->getAmount(),
            ];
        }

        return $rows;
    }

    /**
     * Селекторы замены в снимок строки.
     *
     * @param array<int, StrikeManeuverEffectSelector> $selectors Селекторы.
     *
     * @return list<array{kind: string, state: string}> Снимок.
     */
    public static function replacesOf(array $selectors): array
    {
        $rows = [];
        foreach ($selectors as $selector) {
            $rows[] = ['kind' => $selector->getKind(), 'state' => $selector->getState()];
        }

        return $rows;
    }
}
