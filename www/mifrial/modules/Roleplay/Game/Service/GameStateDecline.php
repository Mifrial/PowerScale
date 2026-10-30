<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Шкала decline: слабость, недееспособность, бессознательность.
 *
 * Проверка только выбирает исход. Движение шкалы (снять старые строки,
 * поставить новую, не понижать при росте) живёт здесь и одинаково для
 * истощения из удара и для прироста из действия.
 */
final class GameStateDecline
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
     */
    public function current(CharacterRuleSlice $slice, array $sheet, CheckSpec $check): ?int
    {
        $outcomes = $check->getOutcomes();
        $codes = $this->codes($slice, $check);
        if ($outcomes !== [] && $codes !== []) {
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
            foreach ($this->stateRows($sheet) as $code) {
                if (isset($byCode[$code])) {
                    $rank = max($rank ?? 0, $byCode[$code]);
                }
            }

            return $rank;
        }

        $rank = null;
        foreach ($this->stateRows($sheet) as $code) {
            $spec = $this->optionalState($slice, $code);
            $rowRank = $spec === null ? null : $this->rank($spec);
            if ($rowRank === null) {
                continue;
            }

            $rank = max($rank ?? 0, $rowRank);
        }

        return $rank;
    }

    /**
     * Движение шкалы: рост только вверх, спад только вниз.
     *
     * @param bool $rose Прирост.
     * @param int|null $current Текущий ранг.
     * @param array{code: string, rank: int}|null $next Исход.
     *
     * @return array<string, mixed>|null Операция или null.
     */
    public function change(bool $rose, ?int $current, ?array $next): ?array
    {
        $nextRank = $next['rank'] ?? 0;
        $currentRank = $current ?? 0;
        if ($rose && $nextRank <= $currentRank) {
            return null;
        }

        if (!$rose && $nextRank >= $currentRank) {
            return null;
        }

        if ($next === null) {
            return ['kind' => 'replaceDecline'];
        }

        return ['kind' => 'replaceDecline', 'stateRuleCode' => $next['code']];
    }

    /**
     * Операции листа по движению шкалы.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed>|null $operation ReplaceDecline или null.
     * @param array<int, string> $declineCodes Коды исходов проверки.
     *
     * @return list<array<string, mixed>> removeState и putState.
     */
    public function operations(
        array $sheet,
        CharacterRuleSlice $slice,
        ?array $operation,
        array $declineCodes = [],
    ): array {
        if ($operation === null) {
            return [];
        }

        $ops = [];
        $states = $sheet['states'] ?? [];
        if (is_array($states)) {
            foreach ($states as $row) {
                if (!is_array($row) || !is_int($row['id'] ?? null)) {
                    continue;
                }

                $code = $row['stateRuleCode'] ?? null;
                if ($declineCodes !== []) {
                    if (is_string($code) && in_array($code, $declineCodes, true)) {
                        $ops[] = ['kind' => 'removeState', 'id' => $row['id']];
                    }

                    continue;
                }

                $spec = is_string($code) ? $this->optionalState($slice, $code) : null;
                if ($spec !== null && $this->rank($spec) !== null) {
                    $ops[] = ['kind' => 'removeState', 'id' => $row['id']];
                }
            }
        }

        $next = $operation['stateRuleCode'] ?? null;
        if (is_string($next) && $next !== '') {
            $ops[] = ['kind' => 'putState', 'stateRuleCode' => $next];
        }

        return $ops;
    }

    /**
     * Вид исхода для сообщения.
     *
     * @param array{code: string, rank: int}|null $next Исход.
     *
     * @return string Вид.
     */
    public function kind(?array $next): string
    {
        return match ($next['rank'] ?? null) {
            1 => 'weakness',
            2 => 'disabled',
            3 => 'unconscious',
            default => 'clear',
        };
    }

    /**
     * Состояние по коду или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return StateSpec|null Состояние.
     */
    private function optionalState(CharacterRuleSlice $slice, string $code): ?StateSpec
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
    private function stateRows(array $sheet): array
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
}
