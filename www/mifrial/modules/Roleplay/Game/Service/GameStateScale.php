<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Чтение шкалы decline: коды исходов, ранги и текущий ранг по листу.
 */
final class GameStateScale
{
    /**
     * Коды исходов проверки или все decline-состояния ревизии.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Проверка.
     *
     * @return list<string> Коды.
     */
    public function codes(CharacterRuleSlice $slice, CheckSpec $check): array
    {
        $codes = [];
        foreach ($check->getOutcomes() as $outcome) {
            $code = $outcome->getStateRuleCode();
            if ($code !== null) {
                $codes[] = $code;
            }
        }

        if ($codes !== []) {
            return array_values(array_unique($codes));
        }

        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $this->rank($spec) !== null) {
                $codes[] = $rule->getCode();
            }
        }

        return $codes;
    }

    /**
     * Ранг состояния по флагам decline_*.
     *
     * @param StateSpec $spec Состояние.
     *
     * @return int|null Ранг 1–3 или null.
     */
    public function rank(StateSpec $spec): ?int
    {
        $flags = [$spec->isDeclineWeakness(), $spec->isDeclineDisabled(), $spec->isDeclineUnconscious()];
        if (count(array_filter($flags)) !== 1) {
            return null;
        }

        if ($spec->isDeclineWeakness()) {
            return 1;
        }

        return $spec->isDeclineDisabled() ? 2 : 3;
    }

    /**
     * Ранг состояния по коду или null, если это не decline.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $code Код состояния.
     *
     * @return int|null Ранг или null.
     */
    public function rankOf(CharacterRuleSlice $slice, ?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $spec = $this->optionalState($slice, $code);

        return $spec === null ? null : $this->rank($spec);
    }

    /**
     * Ранг кода среди исходов проверки.
     *
     * @param CheckSpec $check Проверка.
     * @param string $code Код состояния.
     *
     * @return int|null Ранг или null.
     */
    public function rankInCheck(CheckSpec $check, string $code): ?int
    {
        $rank = 0;
        foreach ($check->getOutcomes() as $outcome) {
            $outcomeCode = $outcome->getStateRuleCode();
            if ($outcomeCode === null) {
                continue;
            }

            $rank++;
            if ($outcomeCode === $code) {
                return $rank;
            }
        }

        return null;
    }

    /**
     * Текущий ранг шкалы по листу.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param CheckSpec $check Проверка.
     *
     * @return int|null Ранг или null.
     *
     * @throws GameInvalidException Если лист битый.
     */
    public function current(CharacterRuleSlice $slice, array $sheet, CheckSpec $check): ?int
    {
        $outcomes = $check->getOutcomes();
        $codes = $this->codes($slice, $check);
        if ($outcomes !== [] && $codes !== []) {
            return $this->currentByOutcome($outcomes, $sheet);
        }

        return $this->currentByRank($slice, $sheet);
    }

    /**
     * Состояние по коду или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return StateSpec|null Состояние.
     */
    public function optionalState(CharacterRuleSlice $slice, string $code): ?StateSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof StateSpec ? $spec : null;
    }

    /**
     * Коды строк состояний.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<int, string> Коды.
     *
     * @throws GameInvalidException Если лист битый.
     */
    public function stateCodes(array $sheet): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        $codes = [];
        foreach ($states as $row) {
            $code = is_array($row) ? ($row['stateRuleCode'] ?? null) : null;
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Текущий ранг по кодам исходов проверки.
     *
     * @param array<int, object> $outcomes Исходы.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return int|null Ранг или null.
     *
     * @throws GameInvalidException Если лист битый.
     */
    private function currentByOutcome(array $outcomes, array $sheet): ?int
    {
        $byCode = [];
        $severity = 0;
        foreach ($outcomes as $outcome) {
            $code = $outcome->getStateRuleCode();
            if ($code === null) {
                continue;
            }

            $severity++;
            $byCode[$code] = $severity;
        }

        $rank = null;
        foreach ($this->stateCodes($sheet) as $code) {
            if (isset($byCode[$code])) {
                $rank = max($rank ?? 0, $byCode[$code]);
            }
        }

        return $rank;
    }

    /**
     * Текущий ранг по decline-спекам строк листа.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return int|null Ранг или null.
     *
     * @throws GameInvalidException Если лист битый.
     */
    private function currentByRank(CharacterRuleSlice $slice, array $sheet): ?int
    {
        $rank = null;
        foreach ($this->stateCodes($sheet) as $code) {
            $spec = $this->optionalState($slice, $code);
            $rowRank = $spec === null ? null : $this->rank($spec);
            if ($rowRank === null) {
                continue;
            }

            $rank = max($rank ?? 0, $rowRank);
        }

        return $rank;
    }



}
