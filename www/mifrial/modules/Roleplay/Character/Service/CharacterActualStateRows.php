<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh
// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Пишет sheet.states: putState дописывает строку, putDamageSplit заменяет остаток и прибавляет истощение.
 */
final class CharacterActualStateRows
{
    /**
     * Форма putState до среза. Чужой kind не трогает.
     *
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если ключи или код.
     */
    public function accept(mixed $operation): void
    {
        if (!is_array($operation) || ($operation['kind'] ?? null) !== 'putState') {
            return;
        }

        $keys = array_keys($operation);
        sort($keys);
        $code = $operation['stateRuleCode'] ?? null;
        if (!is_string($code) || $code === '' || ($operation['kind'] ?? null) !== 'putState') {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        foreach ($keys as $key) {
            $allowed = ['aided', 'bandage', 'clotting', 'heldBy', 'internal', 'kind', 'maim', 'stateRuleCode', 'value'];
            if (!in_array($key, $allowed, true)) {
                throw new CharacterInvalidException('Character patch operation is invalid');
            }
        }
    }

    /**
     * Форма patchState: id и хотя бы одно поле.
     *
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если ключи.
     */
    public function acceptPatch(mixed $operation): void
    {
        if (!is_array($operation) || ($operation['kind'] ?? null) !== 'patchState') {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        if (!$this->hasStateId($operation)) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        $fields = 0;
        foreach (array_keys($operation) as $key) {
            if ($key === 'kind' || $key === 'id') {
                continue;
            }

            $allowed = ['aided', 'bandage', 'clotting', 'dotTurnsLeft', 'heldBy', 'internal', 'maim', 'value'];
            if (!in_array($key, $allowed, true)) {
                throw new CharacterInvalidException('Character patch operation is invalid');
            }

            $fields++;
        }

        if ($fields === 0) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }
    }

    /**
     * Форма removeState: kind и id.
     *
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если ключи.
     */
    public function acceptRemove(mixed $operation): void
    {
        if (
            !is_array($operation)
            || ($operation['kind'] ?? null) !== 'removeState'
            || !$this->hasStateId($operation)
        ) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        foreach (array_keys($operation) as $key) {
            if ($key !== 'kind' && $key !== 'id') {
                throw new CharacterInvalidException('Character patch operation is invalid');
            }
        }
    }

    /**
     * Форма putDamageSplit до среза. Чужой kind не трогает.
     *
     * @param mixed $operation Операция.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если ключи, остаток или частное.
     */
    public function acceptDamage(mixed $operation): void
    {
        if (!is_array($operation) || ($operation['kind'] ?? null) !== 'putDamageSplit') {
            return;
        }

        $this->damageOf($operation);
    }

    /**
     * Список после putState и putDamageSplit в порядке операций.
     *
     * @param array<string, mixed> $sheet Снимок.
     * @param array $operations Список.
     * @param CharacterRuleSlice $slice Срез ревизии.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если карточка, список или значение.
     */
    public function append(array $sheet, array $operations, CharacterRuleSlice $slice): array
    {
        foreach ($operations as $operation) {
            if (is_array($operation) && ($operation['kind'] ?? null) === 'putState') {
                $sheet = $this->appendOne($sheet, $operation, $slice);
            }

            if (is_array($operation) && ($operation['kind'] ?? null) === 'putDamageSplit') {
                $sheet = $this->splitOne($sheet, $operation, $slice);
            }

            if (is_array($operation) && ($operation['kind'] ?? null) === 'patchState') {
                $sheet = $this->patchOne($sheet, $operation, $slice);
            }

            if (is_array($operation) && ($operation['kind'] ?? null) === 'removeState') {
                $sheet = $this->removeOne($sheet, $operation);
            }
        }

        return $sheet;
    }

    /**
     * Снимает строки без уникального id. Создавать такие нельзя.
     *
     * @param array<string, mixed> $sheet Снимок.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если ключ не список.
     */
    public function withoutUnidentified(array $sheet): array
    {
        if (!array_key_exists('states', $sheet)) {
            return $sheet;
        }

        $states = $sheet['states'];
        if (!is_array($states) || !array_is_list($states)) {
            throw new CharacterInvalidException('Character states shape is invalid');
        }

        $kept = [];
        foreach ($states as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            if (is_int($id) && $id >= 1) {
                $kept[] = $row;
            }
        }

        $sheet['states'] = $kept;

        return $sheet;
    }

    /**
     * Одна строка в конец списка.
     *
     * @param array<string, mixed> $sheet Снимок.
     * @param array $operation Уже принятая форма.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если карточка или значение.
     */
    private function appendOne(array $sheet, array $operation, CharacterRuleSlice $slice): array
    {
        $code = $operation['stateRuleCode'];
        $rows = $this->rowsOf($sheet);
        $spec = $this->specOf($slice, $code);
        $this->assertMaimKey($operation, $spec);
        if ($this->isDecline($spec)) {
            $rows = $this->withoutDecline($rows, $slice);
        }

        $row = $this->row($code, $operation, $spec);
        $aggregation = $spec->getAggregation();
        $index = ($aggregation === 'sum' || $aggregation === 'max') ? $this->firstRow($rows, $code) : null;
        if ($index !== null) {
            $existing = $rows[$index];
            $existingId = is_array($existing) ? ($existing['id'] ?? null) : null;
            $row['id'] = is_int($existingId) && $existingId >= 1 ? $existingId : $this->nextId($rows);
            if (isset($existing['value'], $row['value'])) {
                $row['value'] = $this->mergedValue($existing['value'], $row['value'], $aggregation);
            }

            $rows[$index] = $row;
            $sheet['states'] = $rows;

            return $sheet;
        }

        $row['id'] = $this->nextId($rows);
        $rows[] = $row;
        $sheet['states'] = $rows;

        return $sheet;
    }

    /**
     * Упадок сил: на листе остаётся одно такое состояние.
     *
     * @param StateSpec $spec Карточка.
     *
     * @return bool true, если карточка из группы упадка.
     */
    private function isDecline(StateSpec $spec): bool
    {
        return $spec->isDeclineWeakness() || $spec->isDeclineDisabled() || $spec->isDeclineUnconscious();
    }

    /**
     * Снимает другие состояния упадка.
     *
     * @param array<int, mixed> $rows Строки.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<int, mixed> Строки.
     */
    private function withoutDecline(array $rows, CharacterRuleSlice $slice): array
    {
        $kept = [];
        foreach ($rows as $row) {
            $code = is_array($row) ? ($row['stateRuleCode'] ?? null) : null;
            $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
            if ($spec instanceof StateSpec && $this->isDecline($spec)) {
                continue;
            }

            $kept[] = $row;
        }

        return $kept;
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

    /**
     * Живая карточка state.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return StateSpec Spec.
     *
     * @throws CharacterInvalidException Если карточки нет или это не state.
     */
    private function specOf(CharacterRuleSlice $slice, string $code): StateSpec
    {
        if ($slice->hasTombstone($code)) {
            throw new CharacterInvalidException('Character state is tombstoned');
        }

        $rule = $slice->findLive($code);
        $spec = $rule?->getSpec();
        if ($rule === null || $rule->getType() !== 'state' || !$spec instanceof StateSpec) {
            throw new CharacterInvalidException('Character state was not found');
        }

        return $spec;
    }

    /**
     * Строка листа по value_type.
     *
     * @param string $code Код.
     * @param array $operation Операция.
     * @param StateSpec $spec Карточка.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws CharacterInvalidException Если значение не по типу.
     */
    private function row(string $code, array $operation, StateSpec $spec): array
    {
        $valueType = $spec->getValueType();
        if ($valueType === 'flag') {
            return $this->flagRow($code, $operation);
        }

        if ($valueType === 'number') {
            return $this->numberRow($code, $operation);
        }

        if ($valueType === 'dimensional') {
            return $this->withWoundSidecar(
                $operation,
                ['stateRuleCode' => $code, 'value' => $this->dimensional($operation)],
            );
        }

        throw new CharacterInvalidException('Character state value is invalid');
    }

    /**
     * Flag без value.
     *
     * @param string $code Код.
     * @param array $operation Операция.
     *
     * @return array{stateRuleCode: string} Строка.
     *
     * @throws CharacterInvalidException Если value передан.
     */
    private function flagRow(string $code, array $operation): array
    {
        if (array_key_exists('value', $operation)) {
            throw new CharacterInvalidException('Character state value is invalid');
        }

        return $this->withWoundSidecar($operation, ['stateRuleCode' => $code]);
    }

    /**
     * Number с целым value.
     *
     * @param string $code Код.
     * @param array $operation Операция.
     *
     * @return array{stateRuleCode: string, value: int} Строка.
     *
     * @throws CharacterInvalidException Если value не int.
     */
    private function numberRow(string $code, array $operation): array
    {
        $value = $operation['value'] ?? null;
        if (!is_int($value)) {
            throw new CharacterInvalidException('Character state value is invalid');
        }

        return $this->withWoundSidecar($operation, ['stateRuleCode' => $code, 'value' => $value]);
    }

    /**
     * Dimensional: base и size.
     *
     * @param array $operation Операция.
     *
     * @return array{base: int, size: int} Значение.
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function dimensional(array $operation): array
    {
        $value = $operation['value'] ?? null;
        $keys = is_array($value) ? array_keys($value) : [];
        sort($keys);
        $base = is_array($value) ? ($value['base'] ?? null) : null;
        $size = is_array($value) ? ($value['size'] ?? null) : null;
        if ($keys !== ['base', 'size'] || !is_int($base) || !is_int($size)) {
            throw new CharacterInvalidException('Character state value is invalid');
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Остаток и частное одной операции.
     *
     * @param array<string, mixed> $sheet Снимок.
     * @param array $operation Уже принятая форма.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если карточка, список или значение.
     */
    private function splitOne(array $sheet, array $operation, CharacterRuleSlice $slice): array
    {
        $damage = $this->damageOf($operation);
        $remainder = $this->markedRule($slice, true);
        $exhaustion = $this->markedRule($slice, false);
        if ($remainder['code'] === $exhaustion['code']) {
            throw new CharacterInvalidException('Character state was not found');
        }

        $this->assertValueType($remainder['spec'], 'dimensional');
        $this->assertValueType($exhaustion['spec'], 'number');
        $rows = $this->rowsOf($sheet);
        $remainderIndex = $this->onlyRow($rows, $remainder['code']);
        $exhaustionIndex = $this->onlyRow($rows, $exhaustion['code']);
        $nextExhaustion = $this->exhaustionValue($rows, $exhaustionIndex, $damage['quotient']);
        $rows = $this->putRow($rows, $remainderIndex, $remainder['code'], $damage['remainder']);
        if ($nextExhaustion !== null) {
            $rows = $this->putRow($rows, $exhaustionIndex, $exhaustion['code'], $nextExhaustion);
        }

        $sheet['states'] = $rows;

        return $sheet;
    }

    /**
     * Ключи putDamageSplit и уже посчитанные числа.
     *
     * @param mixed $operation Операция.
     *
     * @return array{remainder: array{base: int, size: int}, quotient: int} Числа.
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function damageOf(mixed $operation): array
    {
        $keys = is_array($operation) ? array_keys($operation) : [];
        sort($keys);
        $remainder = is_array($operation) ? ($operation['remainder'] ?? null) : null;
        $remainderKeys = is_array($remainder) ? array_keys($remainder) : [];
        sort($remainderKeys);
        $base = is_array($remainder) ? ($remainder['base'] ?? null) : null;
        $size = is_array($remainder) ? ($remainder['size'] ?? null) : null;
        $quotient = is_array($operation) ? ($operation['quotient'] ?? null) : null;
        $shaped = $keys === ['kind', 'quotient', 'remainder'] && ($operation['kind'] ?? null) === 'putDamageSplit';
        $numbers = $remainderKeys === ['base', 'size'] && is_int($base) && is_int($size) && is_int($quotient);
        if (!$shaped || !$numbers) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        return ['remainder' => ['base' => $base, 'size' => $size], 'quotient' => $quotient];
    }

    /**
     * Единственная живая карточка флага.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param bool $remainder true — остаток, false — истощение.
     *
     * @return array{code: string, spec: StateSpec} Карточка.
     *
     * @throws CharacterInvalidException Если карточки нет или их две.
     */
    private function markedRule(CharacterRuleSlice $slice, bool $remainder): array
    {
        $found = null;
        foreach ($slice->getLiveRules() as $rule) {
            $found = $this->keepMarked($found, $rule, $remainder);
        }

        if ($found === null) {
            throw new CharacterInvalidException('Character state was not found');
        }

        return $found;
    }

    /**
     * Учитывает карточку, если флаг включён.
     *
     * @param array{code: string, spec: StateSpec}|null $found Уже найденная.
     * @param mixed $rule Пункт среза.
     * @param bool $remainder true — остаток.
     *
     * @return array{code: string, spec: StateSpec}|null Карточка или прежняя.
     *
     * @throws CharacterInvalidException Если флаг встретился второй раз.
     */
    private function keepMarked(array|null $found, mixed $rule, bool $remainder): array|null
    {
        if (!$rule instanceof CharacterResolvedRule) {
            return $found;
        }

        $spec = $rule->getSpec();
        $marked = $rule->getType() === 'state' && $spec instanceof StateSpec && $this->hasMark($spec, $remainder);
        if (!$marked || !$spec instanceof StateSpec) {
            return $found;
        }

        if ($found !== null) {
            throw new CharacterInvalidException('Character state was not found');
        }

        return ['code' => $rule->getCode(), 'spec' => $spec];
    }

    /**
     * Флаг остатка или истощения.
     *
     * @param StateSpec $spec Карточка.
     * @param bool $remainder true — остаток.
     *
     * @return bool true, если флаг включён.
     */
    private function hasMark(StateSpec $spec, bool $remainder): bool
    {
        return $remainder ? $spec->isDamageRemainder() : $spec->isDamageExhaustion();
    }

    /**
     * value_type карточки.
     *
     * @param StateSpec $spec Карточка.
     * @param string $expected Ожидаемый тип.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если тип другой.
     */
    private function assertValueType(StateSpec $spec, string $expected): void
    {
        if ($spec->getValueType() !== $expected) {
            throw new CharacterInvalidException('Character state value is invalid');
        }
    }

    /**
     * Единственная строка кода или null.
     *
     * @param array<int, mixed> $rows Строки.
     * @param string $code Код.
     *
     * @return int|null Индекс.
     *
     * @throws CharacterInvalidException Если код встретился второй раз.
     */
    private function onlyRow(array $rows, string $code): int|null
    {
        $found = null;
        foreach ($rows as $index => $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            if ($found !== null) {
                throw new CharacterInvalidException('Character state was not found');
            }

            $found = $index;
        }

        return $found;
    }

    /**
     * Первая строка кода.
     *
     * @param array<int, mixed> $rows Строки.
     * @param string $code Код.
     *
     * @return int|null Индекс.
     */
    private function firstRow(array $rows, string $code): int|null
    {
        foreach ($rows as $index => $row) {
            if (is_array($row) && ($row['stateRuleCode'] ?? null) === $code) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Новое истощение. Ноль — строку не менять.
     *
     * @param array<int, mixed> $rows Строки.
     * @param int|null $index Индекс или null.
     * @param int $quotient Частное.
     *
     * @return int|null Значение или null, если писать нечего.
     *
     * @throws CharacterInvalidException Если лежащее значение не int.
     */
    private function exhaustionValue(array $rows, int|null $index, int $quotient): int|null
    {
        if ($index === null) {
            return $quotient === 0 ? null : $quotient;
        }

        $row = $rows[$index];
        $value = is_array($row) ? ($row['value'] ?? null) : null;
        if (!is_int($value)) {
            throw new CharacterInvalidException('Character state value is invalid');
        }

        if ($quotient === 0) {
            return null;
        }

        return $value + $quotient;
    }

    /**
     * Замена на индексе или новая строка в конце.
     *
     * @param array<int, mixed> $rows Строки.
     * @param int|null $index Индекс или null.
     * @param string $code Код.
     * @param int|array{base: int, size: int} $value Значение.
     *
     * @return array<int, mixed> Строки.
     */
    private function putRow(array $rows, int|null $index, string $code, int|array $value): array
    {
        $row = ['stateRuleCode' => $code, 'value' => $value];
        if ($index === null) {
            $row['id'] = $this->nextId($rows);
            $rows[] = $row;

            return $rows;
        }

        $existing = $rows[$index];
        $existingId = is_array($existing) ? ($existing['id'] ?? null) : null;
        $row['id'] = is_int($existingId) && $existingId >= 1 ? $existingId : $this->nextId($rows);
        $rows[$index] = $row;

        return $rows;
    }

    /**
     * Sidecar раны, если поля переданы.
     *
     * @param array<string, mixed> $operation Операция.
     * @param array<string, mixed> $row Строка значения.
     *
     * @return array<string, mixed> Строка.
     */
    private function withWoundSidecar(array $operation, array $row): array
    {
        foreach (['bandage', 'clotting', 'internal', 'aided', 'heldBy', 'maim'] as $field) {
            if (array_key_exists($field, $operation)) {
                $row[$field] = $operation[$field];
            }
        }

        return $row;
    }

    /**
     * Sidecar увечья только на карточке maim. Ключ опционален.
     *
     * @param array<string, mixed> $operation Операция.
     * @param StateSpec $spec Карточка.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если ключ или форма.
     */
    private function assertMaimKey(array $operation, StateSpec $spec): void
    {
        if (!array_key_exists('maim', $operation)) {
            return;
        }

        if (!$spec->isMaim()) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        $maim = $operation['maim'];
        $keys = is_array($maim) ? array_keys($maim) : [];
        $permanent = is_array($maim) ? ($maim['permanent'] ?? null) : null;
        if (!is_array($maim) || array_is_list($maim) || !is_bool($permanent)) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }

        foreach ($keys as $key) {
            if (!in_array($key, ['disfiguring', 'healTotal', 'healUnit', 'lethal', 'permanent'], true)) {
                throw new CharacterInvalidException('Character patch operation is invalid');
            }
        }

        if ($permanent) {
            return;
        }

        $healTotal = $maim['healTotal'] ?? null;
        $healUnit = $maim['healUnit'] ?? null;
        $units = ['days', 'decades', 'months', 'years'];
        if (!is_int($healTotal) || $healTotal < 1 || !is_string($healUnit) || !in_array($healUnit, $units, true)) {
            throw new CharacterInvalidException('Character patch operation is invalid');
        }
    }

    /**
     * Следующий id списка.
     *
     * @param array<int, mixed> $rows Строки.
     *
     * @return int Id.
     */
    private function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            if (is_int($id) && $id > $max) {
                $max = $id;
            }
        }

        return $max + 1;
    }

    /**
     * Патч строки по id.
     *
     * @param array<string, mixed> $sheet Снимок.
     * @param array<string, mixed> $operation Операция.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если строки нет.
     */
    private function patchOne(array $sheet, array $operation, CharacterRuleSlice $slice): array
    {
        $rows = $this->rowsOf($sheet);
        $index = $this->indexById($rows, (int) $operation['id']);
        $code = $rows[$index]['stateRuleCode'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new CharacterInvalidException('Character state was not found');
        }

        $this->assertMaimKey($operation, $this->specOf($slice, $code));

        foreach (['value', 'bandage', 'clotting', 'internal', 'aided', 'heldBy', 'dotTurnsLeft', 'maim'] as $field) {
            if (array_key_exists($field, $operation)) {
                $rows[$index][$field] = $operation[$field];
            }
        }

        $sheet['states'] = $rows;

        return $sheet;
    }

    /**
     * Удаление строки по id.
     *
     * @param array<string, mixed> $sheet Снимок.
     * @param array<string, mixed> $operation Операция.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws CharacterInvalidException Если строки нет.
     */
    private function removeOne(array $sheet, array $operation): array
    {
        $rows = $this->rowsOf($sheet);
        $index = $this->indexById($rows, (int) $operation['id']);
        array_splice($rows, $index, 1);
        $sheet['states'] = $rows;

        return $sheet;
    }

    /**
     * Уникальный id строки листа.
     *
     * @param array<string, mixed> $operation Операция.
     *
     * @return bool true, если id задан.
     */
    private function hasStateId(array $operation): bool
    {
        $id = $operation['id'] ?? null;

        return is_int($id) && $id >= 1;
    }

    /**
     * Индекс строки с id.
     *
     * @param array<int, mixed> $rows Строки.
     * @param int $id Id.
     *
     * @return int Индекс.
     *
     * @throws CharacterInvalidException Если строки нет или две.
     */
    private function indexById(array $rows, int $id): int
    {
        $found = null;
        foreach ($rows as $index => $row) {
            if (!is_array($row) || ($row['id'] ?? null) !== $id) {
                continue;
            }

            if ($found !== null) {
                throw new CharacterInvalidException('Character state was not found');
            }

            $found = $index;
        }

        if (!is_int($found)) {
            throw new CharacterInvalidException('Character state was not found');
        }

        return $found;
    }

    /**
     * Уже лежащий список или пустой.
     *
     * @param array<string, mixed> $sheet Снимок.
     *
     * @return array<int, mixed> Строки.
     *
     * @throws CharacterInvalidException Если ключ не список.
     */
    private function rowsOf(array $sheet): array
    {
        if (!array_key_exists('states', $sheet)) {
            return [];
        }

        $states = $sheet['states'];
        if (!is_array($states) || !array_is_list($states)) {
            throw new CharacterInvalidException('Character states shape is invalid');
        }

        return $states;
    }
}
