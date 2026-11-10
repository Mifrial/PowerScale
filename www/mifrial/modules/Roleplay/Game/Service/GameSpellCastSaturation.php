<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationResult;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationSource;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Насыщение проверки сотворения: источник, шаги и прирост Мощи.
 */
final class GameSpellCastSaturation
{
    use GameSpellCastAbilityLevelTrait;

    /**
     * Первый изученный источник насыщения.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param CharacterChoices $model Выборы кастера.
     * @param array<string, mixed> $abilityLevels Эффективные уровни.
     *
     * @return GameSpellCastSaturationSource|null Источник или null.
     */
    public function source(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $abilityLevels,
    ): ?GameSpellCastSaturationSource {
        foreach ($model->getAbilities() as $ability) {
            $spec = $this->learnedAbility($slice, $abilityLevels, $ability);
            if ($spec === null) {
                continue;
            }

            $saturation = $spec->getBase()->getSpellSaturation();
            if ($saturation === null || $saturation->getRatingPerStep() <= 0) {
                continue;
            }

            return new GameSpellCastSaturationSource($ability->getRuleCode(), $saturation);
        }

        return null;
    }

    /**
     * Доступный выбор шагов по итогу проверки.
     *
     * @param GameSpellCastSaturationSource|null $source Источник или null.
     * @param array<string, mixed> $check Итог проверки.
     *
     * @return GameSpellCastSaturationPlan Выбор.
     */
    public function plan(?GameSpellCastSaturationSource $source, array $check): GameSpellCastSaturationPlan
    {
        $rating = $this->ratingOf($check);
        $maxSteps = 0;
        if (
            $source !== null
            && ($check['castOk'] ?? false) === true
            && ($check['needsCheck'] ?? false) === true
            && $rating >= $source->getMinRating()
        ) {
            $maxSteps = intdiv($rating, $source->getRatingPerStep());
        }

        return new GameSpellCastSaturationPlan($source, $rating, $maxSteps);
    }

    /**
     * Применяет выбранные шаги и считает остаток успеха.
     *
     * @param GameSpellCastSaturationPlan $plan Выбор.
     * @param int $steps Выбранные шаги.
     *
     * @return GameSpellCastSaturationResult Итог.
     *
     * @throws GameInvalidException Если шаги вне выбора.
     */
    public function apply(GameSpellCastSaturationPlan $plan, int $steps): GameSpellCastSaturationResult
    {
        if ($steps < 0 || $steps > $plan->getMaxSteps()) {
            throw new GameInvalidException('Game spell saturation steps are invalid');
        }

        $source = $plan->getSource();
        if ($steps > 0 && $source === null) {
            throw new GameInvalidException('Game spell saturation source is missing');
        }

        $ratingPerStep = $source?->getRatingPerStep() ?? 0;
        $powerPerStep = $source?->getPowerPerStep() ?? 0;
        $ratingSpent = $steps * $ratingPerStep;

        return new GameSpellCastSaturationResult(
            $source,
            $steps,
            $ratingSpent,
            $steps * $powerPerStep,
            $plan->getRating() - $ratingSpent,
        );
    }

    /**
     * Успех проверки из её итога.
     *
     * @param array<string, mixed> $check Итог.
     *
     * @return int Успех.
     */
    private function ratingOf(array $check): int
    {
        $roll = $check['roll'] ?? null;
        $rating = is_array($roll) ? ($roll['rating'] ?? null) : null;

        return is_int($rating) ? $rating : 0;
    }
}
