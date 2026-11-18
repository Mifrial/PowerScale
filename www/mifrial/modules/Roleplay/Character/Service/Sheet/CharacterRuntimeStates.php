<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Сливает временные состояния цели с хранимыми строками листа.
 *
 * Временные строки приходят из наложенных эффектов игры и в лист не пишутся.
 * При расчёте они не должны считаться второй раз: строка с тем же кодом —
 * это то же состояние. Слияние повторяет правила записи
 * (`CharacterActualStateRows`): флаг — одна строка, `max`/`sum` — одно
 * значение, упадок — только старший ранг, `suppressed_state_codes`
 * снимает подавленные строки. `independent` без значения (раны, повреждения)
 * остаётся отдельными строками.
 *
 * Хранимые строки без временных не трогаются: это документ листа, а не
 * повод нормализовать его на чтении.
 */
final class CharacterRuntimeStates
{
    /**
     * Лист с временными состояниями, слитыми с хранимыми.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $sheet Документ листа.
     * @param array<int, array<string, mixed>> $runtimeStates Строки эффектов.
     *
     * @return array<string, mixed> Лист.
     */
    public function merge(CharacterRuleSlice $slice, array $sheet, array $runtimeStates): array
    {
        $rows = $this->rows($sheet);
        $incoming = $this->incoming($rows, $runtimeStates);
        if ($incoming === []) {
            return $sheet;
        }

        $merged = $this->byCode($slice, [...$rows, ...$incoming]);
        $merged = $this->decline($slice, $merged);
        $merged = $this->suppress($slice, $merged);

        $sheet['states'] = $merged;

        return $sheet;
    }

    /**
     * Строки состояний листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function rows(array $sheet): array
    {
        $rows = $sheet['states'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $kept = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $kept[] = $row;
            }
        }

        return $kept;
    }

    /**
     * Временные строки, которых ещё нет в листе.
     *
     * Повторный пересчёт по уже дополненному листу не должен дублировать
     * строки: их узнают по `runtimeId`.
     *
     * @param array<int, array<string, mixed>> $rows Строки листа.
     * @param array<int, array<string, mixed>> $runtimeStates Временные строки.
     *
     * @return list<array<string, mixed>> Новые строки.
     */
    private function incoming(array $rows, array $runtimeStates): array
    {
        $known = [];
        foreach ($rows as $row) {
            $id = $row['runtimeId'] ?? null;
            if (is_string($id)) {
                $known[$id] = true;
            }
        }

        $incoming = [];
        foreach ($runtimeStates as $row) {
            $id = $row['runtimeId'] ?? null;
            if (is_string($id) && isset($known[$id])) {
                continue;
            }

            $incoming[] = $row;
            if (is_string($id)) {
                $known[$id] = true;
            }
        }

        return $incoming;
    }

    /**
     * Одна эффективная строка на код, где это требует спека.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, array<string, mixed>> $rows Строки листа и эффектов.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function byCode(CharacterRuleSlice $slice, array $rows): array
    {
        $groups = [];
        $order = [];
        foreach ($rows as $row) {
            $code = $row['stateRuleCode'] ?? null;
            $key = is_string($code) && $code !== '' ? 'code:' . $code : 'row:' . count($order);
            if (!isset($groups[$key])) {
                $groups[$key] = [];
                $order[] = $key;
            }

            $groups[$key][] = $row;
        }

        $merged = [];
        foreach ($order as $key) {
            $one = $this->one($slice, $key, $groups[$key]);
            if ($one !== null) {
                $merged[] = $one;

                continue;
            }

            foreach ($groups[$key] as $row) {
                $merged[] = $row;
            }
        }

        return $merged;
    }

    /**
     * Одна строка группы или null, если группа остаётся как есть.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $key Ключ группы.
     * @param array<int, array<string, mixed>> $group Строки одного кода.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    private function one(CharacterRuleSlice $slice, string $key, array $group): ?array
    {
        if (!str_starts_with($key, 'code:') || !$this->hasRuntime($group)) {
            return null;
        }

        $spec = $this->spec($slice, substr($key, 5));
        if (!$spec instanceof StateSpec) {
            return null;
        }

        if ($spec->getValueType() === 'flag') {
            return $group[0];
        }

        $aggregation = $spec->getAggregation();
        if ($aggregation !== 'sum' && $aggregation !== 'max') {
            return null;
        }

        $row = $group[0];
        $value = null;
        foreach ($group as $item) {
            $value = $this->mergedValue($value, $item['value'] ?? null, $aggregation);
        }

        if ($value !== null) {
            $row['value'] = $value;
        }

        return $row;
    }

    /**
     * В группе есть временная строка.
     *
     * @param array<int, array<string, mixed>> $group Строки.
     *
     * @return bool true, если строка без id.
     */
    private function hasRuntime(array $group): bool
    {
        foreach ($group as $row) {
            if (!$this->stored($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Строка записана в лист.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return bool true, если есть id.
     */
    private function stored(array $row): bool
    {
        return is_int($row['id'] ?? null);
    }

    /**
     * В листе остаётся только старший ранг упадка.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function decline(CharacterRuleSlice $slice, array $rows): array
    {
        $best = null;
        $bestRank = 0;
        foreach ($rows as $index => $row) {
            $spec = $this->specOf($slice, $row);
            $rank = $spec === null ? null : $this->rank($spec);
            if ($rank === null) {
                continue;
            }

            if ($rank > $bestRank) {
                $best = $index;
                $bestRank = $rank;

                continue;
            }

            if ($rank === $bestRank && $best !== null && $this->stored($row) && !$this->stored($rows[$best])) {
                $best = $index;
            }
        }

        if ($best === null) {
            return $rows;
        }

        $kept = [];
        foreach ($rows as $index => $row) {
            $spec = $this->specOf($slice, $row);
            if ($index !== $best && $spec !== null && $this->rank($spec) !== null) {
                continue;
            }

            $kept[] = $row;
        }

        return $kept;
    }

    /**
     * Снимает строки, которые подавляет другая эффективная строка.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function suppress(CharacterRuleSlice $slice, array $rows): array
    {
        $present = [];
        foreach ($rows as $row) {
            $code = $row['stateRuleCode'] ?? null;
            if (is_string($code)) {
                $present[$code] = true;
            }
        }

        $dropped = [];
        foreach ($rows as $row) {
            $spec = $this->specOf($slice, $row);
            if ($spec === null) {
                continue;
            }

            foreach ($spec->getSuppressedStateCodes() as $code) {
                if (is_string($code) && isset($present[$code])) {
                    $dropped[$code] = true;
                }
            }
        }

        if ($dropped === []) {
            return $rows;
        }

        $kept = [];
        foreach ($rows as $row) {
            $code = $row['stateRuleCode'] ?? null;
            if (is_string($code) && isset($dropped[$code])) {
                continue;
            }

            $kept[] = $row;
        }

        return $kept;
    }

    /**
     * Спека состояния строки или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка.
     *
     * @return StateSpec|null Спека.
     */
    private function specOf(CharacterRuleSlice $slice, array $row): ?StateSpec
    {
        $code = $row['stateRuleCode'] ?? null;

        return is_string($code) ? $this->spec($slice, $code) : null;
    }

    /**
     * Живая спека state по коду или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return StateSpec|null Спека.
     */
    private function spec(CharacterRuleSlice $slice, string $code): ?StateSpec
    {
        $spec = $slice->findLive($code)?->getSpec();

        return $spec instanceof StateSpec ? $spec : null;
    }

    /**
     * Ранг упадка по флагам decline_*.
     *
     * @param StateSpec $spec Спека.
     *
     * @return int|null Ранг 1–3 или null.
     */
    private function rank(StateSpec $spec): ?int
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
     * Сумма или максимум значения.
     *
     * @param mixed $current Лежащее.
     * @param mixed $incoming Новое.
     * @param string $aggregation sum или max.
     *
     * @return mixed Значение.
     */
    private function mergedValue(mixed $current, mixed $incoming, string $aggregation): mixed
    {
        if ($current === null) {
            return $incoming;
        }

        if ($incoming === null) {
            return $current;
        }

        if (is_int($current) && is_int($incoming)) {
            return $aggregation === 'max' ? max($current, $incoming) : $current + $incoming;
        }

        $currentBase = is_array($current) ? ($current['base'] ?? null) : null;
        $currentSize = is_array($current) ? ($current['size'] ?? null) : null;
        $incomingBase = is_array($incoming) ? ($incoming['base'] ?? null) : null;
        $incomingSize = is_array($incoming) ? ($incoming['size'] ?? null) : null;
        if (!is_int($currentBase) || !is_int($currentSize) || !is_int($incomingBase) || !is_int($incomingSize)) {
            return $incoming;
        }

        if ($aggregation === 'max') {
            return $incomingBase > $currentBase ? $incoming : $current;
        }

        if ($currentSize !== $incomingSize) {
            return $incoming;
        }

        return ['base' => $currentBase + $incomingBase, 'size' => $currentSize];
    }
}
