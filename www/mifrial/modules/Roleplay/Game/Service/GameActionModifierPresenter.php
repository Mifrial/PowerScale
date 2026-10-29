<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ModifierOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamageOutcomeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SkipAfterActionOutcomeEffect;

/**
 * Человеческая подпись модификатора действия: только по форме эффекта, без кодов правил.
 */
final class GameActionModifierPresenter
{
    /**
     * Подпись для формы и чата.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param ActionModifierEffect $effect Модификатор.
     *
     * @return string Текст.
     */
    public function summary(CharacterRuleSlice $slice, ActionModifierEffect $effect): string
    {
        $effects = $this->effects($slice, $effect->getEffects());
        $check = $effect->getCheck();
        if ($check === null) {
            return $effects;
        }

        return sprintf(
            '%s против %d — %s.',
            $this->ruleName($slice, $check->getCode()),
            $check->getDifficulty(),
            $effects,
        );
    }

    /**
     * Подпись последствий.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param array<int, ModifierOutcomeEffect> $effects Последствия.
     *
     * @return string Текст.
     */
    public function effects(CharacterRuleSlice $slice, array $effects): string
    {
        $lines = [];
        foreach ($effects as $effect) {
            $lines[] = match (true) {
                $effect instanceof SelfDamageOutcomeEffect => $this->selfDamage($slice, $effect),
                $effect instanceof SkipAfterActionOutcomeEffect => 'помеха действия снимается',
                default => $effect->getType(),
            };
        }

        return implode(', ', $lines);
    }

    /**
     * Подпись урона по себе.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param SelfDamageOutcomeEffect $effect Последствие.
     *
     * @return string Текст.
     */
    private function selfDamage(CharacterRuleSlice $slice, SelfDamageOutcomeEffect $effect): string
    {
        $internal = $effect->isIgnoreDefense() ? 'внутренний ' : '';
        $delta = $effect->getSizeDelta();
        $shift = $delta === 0
            ? ''
            : sprintf(' ([сила удара]%s%d)', $delta < 0 ? '↓' : '↑', abs($delta));

        return sprintf(
            '%s%s урон по себе%s',
            $internal,
            mb_strtolower($this->ruleName($slice, $effect->getDamageTypeCode())),
            $shift,
        );
    }

    /**
     * Имя правила или его код.
     *
     * @param CharacterRuleSlice $slice Срез правил игры.
     * @param string $code Код.
     *
     * @return string Имя.
     */
    private function ruleName(CharacterRuleSlice $slice, string $code): string
    {
        return $slice->findLive($code)?->getName() ?? $code;
    }
}
