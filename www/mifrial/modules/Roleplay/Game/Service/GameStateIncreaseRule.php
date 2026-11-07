<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Связка «состояние -> проверка -> исход» для проверки прироста.
 *
 * Читает только спеку: кодов правил и логики конкретного состояния не знает.
 */
final class GameStateIncreaseRule
{
    /**
     * Проверка, объявленная состоянием.
     *
     * @param CharacterRuleSlice $slice Срез листа.
     * @param string $stateCode Код состояния.
     * @param string $checkCode Код проверки из спеки состояния.
     *
     * @return CheckSpec Карточка проверки.
     *
     * @throws GameInvalidException Если связка битая.
     */
    public function check(CharacterRuleSlice $slice, string $stateCode, string $checkCode): CheckSpec
    {
        $state = $this->stateOf($slice, $stateCode);

        return $this->linkedCheck($slice, $state, $stateCode, $checkCode);
    }

    /**
     * Состояние исхода по рейтингу проверки или null.
     *
     * @param CharacterRuleSlice $slice Срез листа.
     * @param CheckSpec $check Карточка проверки.
     * @param int $rating Рейтинг.
     *
     * @return string|null Код состояния или null.
     *
     * @throws GameInvalidException Если состояние исхода не живое.
     */
    public function outcome(CharacterRuleSlice $slice, CheckSpec $check, int $rating): ?string
    {
        foreach ($check->getOutcomes() as $outcome) {
            if (!$outcome->matches($rating)) {
                continue;
            }

            $code = $outcome->getStateRuleCode();
            if ($code === null || $code === '') {
                return null;
            }

            $state = $slice->findLive($code)?->getSpec();
            if (!$state instanceof StateSpec) {
                throw new GameInvalidException('Game state increase outcome is invalid');
            }

            return $code;
        }

        return null;
    }

    /**
     * Живое состояние с суммируемым числовым значением.
     *
     * @param CharacterRuleSlice $slice Срез листа.
     * @param string $stateCode Код состояния.
     *
     * @return StateSpec Состояние.
     *
     * @throws GameInvalidException Если состояние не подходит.
     */
    private function stateOf(CharacterRuleSlice $slice, string $stateCode): StateSpec
    {
        $state = $slice->findLive($stateCode)?->getSpec();
        if (!$state instanceof StateSpec) {
            throw new GameInvalidException('Game state increase is invalid');
        }

        if ($state->getValueType() !== 'number' || $state->getAggregation() !== 'sum') {
            throw new GameInvalidException('Game state increase is invalid');
        }

        return $state;
    }

    /**
     * Проверка, привязанная к состоянию.
     *
     * @param CharacterRuleSlice $slice Срез листа.
     * @param StateSpec $state Состояние.
     * @param string $stateCode Код состояния.
     * @param string $checkCode Код проверки.
     *
     * @return CheckSpec Карточка проверки.
     *
     * @throws GameInvalidException Если связка битая.
     */
    private function linkedCheck(
        CharacterRuleSlice $slice,
        StateSpec $state,
        string $stateCode,
        string $checkCode,
    ): CheckSpec {
        if ($state->getIncreaseCheckCode() !== $checkCode && $state->getCheckCode() !== $checkCode) {
            throw new GameInvalidException('Game state increase check is invalid');
        }

        $rule = $slice->findLive($checkCode);
        $check = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$check instanceof CheckSpec) {
            throw new GameInvalidException('Game state increase check is invalid');
        }

        $difficulty = $check->getDifficulty();
        if ($difficulty->getKind() !== 'from_state' || $difficulty->getStateCode() !== $stateCode) {
            throw new GameInvalidException('Game state increase check is invalid');
        }

        return $check;
    }
}
