<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

/**
 * Жетоны концентрации: ресурс, допустимость проверки и максимум траты.
 */
final class GameConcentration
{
    private readonly CharacterResourceStorage $storage;

    /**
     * Создаёт правила.
     *
     * @return void
     */
    public function __construct()
    {
        $this->storage = new CharacterResourceStorage();
    }

    /**
     * Код ресурса-жетона проверки или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return string|null Код.
     */
    public function resourceCode(CharacterRuleSlice $slice, array $sheet): ?string
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            return null;
        }

        foreach ($this->storage->parseRows($rows, $slice) as $row) {
            $spec = $this->specOf($slice, $row->getRuleCode());
            if ($spec instanceof ResourceSpec && $spec->isCheckToken()) {
                return $row->getRuleCode();
            }
        }

        return null;
    }

    /**
     * Текущий запас жетонов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Ресурс.
     *
     * @return int Запас.
     */
    public function current(CharacterRuleSlice $slice, array $sheet, string $code): int
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            return 0;
        }

        foreach ($this->storage->parseRows($rows, $slice) as $row) {
            if ($row->getRuleCode() !== $code) {
                continue;
            }

            $current = $row->getCurrent();

            return $current instanceof ScalarResourceValue ? $current->getValue() : 0;
        }

        return 0;
    }

    /**
     * Максимум жетонов на одну проверку.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $levels Уровни способностей.
     * @param int $current Текущий запас.
     *
     * @return int Максимум.
     */
    public function maxSpend(CharacterRuleSlice $slice, array $levels, int $current): int
    {
        $peak = 0;
        foreach ($slice->getLiveRules() as $rule) {
            $base = $this->abilityBaseOf($rule->getSpec());
            if ($base === null || !$base->isPeakConcentration()) {
                continue;
            }

            $peak = max($peak, is_int($levels[$rule->getCode()] ?? null) ? $levels[$rule->getCode()] : 0);
        }

        return max(0, min($current, 1 + $peak));
    }

    /**
     * Проверка права на жетон по спеке.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка проверки.
     * @param string|null $characteristic Действующая характеристика.
     * @param array<string, mixed> $levels Уровни способностей.
     * @param int $actionTurns Длительность действия в ходах.
     *
     * @return bool Можно.
     */
    public function allows(
        CharacterRuleSlice $slice,
        string $checkCode,
        ?string $characteristic,
        array $levels,
        int $actionTurns = 1,
    ): bool {
        if ($actionTurns > 1 && !$this->hasFlag($slice, 'longTension')) {
            return false;
        }

        return $this->chainAllows($slice, $checkCode, $characteristic);
    }

    /**
     * Списание жетонов проверкой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $levels Уровни способностей.
     * @param string $code Ресурс.
     * @param int $amount Сколько.
     *
     * @return int Сколько списано.
     *
     * @throws GameInvalidException Если жетонов не хватает.
     */
    public function assertAmount(
        CharacterRuleSlice $slice,
        array $sheet,
        array $levels,
        string $code,
        int $amount,
    ): int {
        $current = $this->current($slice, $sheet, $code);
        $max = $this->maxSpend($slice, $levels, $current);
        if ($amount < 1 || $amount > $max) {
            throw new GameInvalidException('Game concentration amount is invalid');
        }

        return $amount;
    }

    /**
     * Проверка или её родительская цепочка несёт жетон.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка проверки.
     * @param string|null $characteristic Действующая характеристика.
     *
     * @return bool Можно.
     */
    private function chainAllows(
        CharacterRuleSlice $slice,
        string $checkCode,
        ?string $characteristic,
    ): bool {
        if ($this->checkSpecOf($slice, $checkCode) === null) {
            return false;
        }

        return $this->chainHasToken($slice, $checkCode)
            || $this->characteristicAllows($slice, $characteristic);
    }

    /**
     * Цепочка проверок несёт жетон.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка проверки.
     *
     * @return bool Есть.
     */
    private function chainHasToken(CharacterRuleSlice $slice, string $checkCode): bool
    {
        $code = $checkCode;
        $guard = 0;
        while ($code !== null && $guard < 20) {
            $current = $this->checkSpecOf($slice, $code);
            if ($current === null) {
                break;
            }

            if ($this->tokenInCheck($slice, $current)) {
                return true;
            }

            $code = $current->getParentCheckCode();
            $guard++;
        }

        return false;
    }

    /**
     * Проверка сама даёт жетон.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Карточка проверки.
     *
     * @return bool Есть.
     */
    private function tokenInCheck(CharacterRuleSlice $slice, CheckSpec $check): bool
    {
        return $check->isConcentrationToken()
            || ($check->isWillpower() && $this->hasFlag($slice, 'willFocus'));
    }

    /**
     * Характеристика сама даёт жетон.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $characteristic Действующая характеристика.
     *
     * @return bool Можно.
     */
    private function characteristicAllows(CharacterRuleSlice $slice, ?string $characteristic): bool
    {
        $charSpec = $characteristic === null ? null : $this->specOf($slice, $characteristic);

        return $charSpec instanceof CharacteristicSpec && $charSpec->isConcentrationToken();
    }

    /**
     * Спека проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return CheckSpec|null Спека.
     */
    private function checkSpecOf(CharacterRuleSlice $slice, string $code): ?CheckSpec
    {
        $spec = $this->specOf($slice, $code);

        return $spec instanceof CheckSpec ? $spec : null;
    }

    /**
     * Спека правила.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return mixed Спека или null.
     */
    private function specOf(CharacterRuleSlice $slice, string $code): mixed
    {
        $rule = $slice->findLive($code);
        if (!$rule instanceof CharacterResolvedRule || $rule->isSpecBroken()) {
            return null;
        }

        return $rule->getSpec();
    }

    /**
     * Живая способность с признаком.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $flag longTension или willFocus.
     *
     * @return bool Есть.
     */
    private function hasFlag(CharacterRuleSlice $slice, string $flag): bool
    {
        foreach ($slice->getLiveRules() as $rule) {
            $base = $this->abilityBaseOf($rule->getSpec());
            if ($base !== null && $this->flagSet($base, $flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Способность несёт названный признак.
     *
     * @param AbilityBase $base База способности.
     * @param string $flag longTension или willFocus.
     *
     * @return bool Есть.
     */
    private function flagSet(AbilityBase $base, string $flag): bool
    {
        return ($flag === 'willFocus' && $base->isWillFocus())
            || ($flag === 'longTension' && $base->isLongTension());
    }

    /**
     * Общие поля способности, если спека их несёт.
     *
     * @param mixed $spec Спека правила.
     *
     * @return AbilityBase|null База или null.
     */
    private function abilityBaseOf(mixed $spec): ?AbilityBase
    {
        return $spec instanceof AbilityWithBase ? $spec->getBase() : null;
    }
}
