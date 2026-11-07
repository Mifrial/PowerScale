<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Resource;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\DimensionalResourceValue;
use Mifrial\Roleplay\Character\Dto\ResourceLimitBonus;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Dto\ResourceValue;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Читает native-строку ресурса и её live spec.
 */
final class CharacterResourceRowReader
{
    /**
     * Разбирает одну сохранённую строку.
     *
     * @param mixed $row Native row.
     * @param CharacterRuleSlice $slice Live rule slice.
     *
     * @return ResourceRow Validated row.
     *
     * @throws CharacterInvalidException If the row or live rule is invalid.
     */
    public function readRow(mixed $row, CharacterRuleSlice $slice): ResourceRow
    {
        [$ruleCode, $value, $base, $bonuses] = $this->parts($row, $slice);

        return new ResourceRow(
            $ruleCode,
            $this->readValue($value, $this->resourceSpec($slice, $ruleCode)),
            $base,
            $bonuses,
        );
    }

    /**
     * Разбирает native-значение по variant live-ресурса.
     *
     * @param mixed $value Native current value.
     * @param ResourceSpec $spec Live resource spec.
     *
     * @return ResourceValue Typed value.
     *
     * @throws CharacterInvalidException If the native shape mismatches.
     */
    public function readValue(mixed $value, ResourceSpec $spec): ResourceValue
    {
        if (!$spec->isDimensional()) {
            return $this->scalarValue($value);
        }

        return $this->dimensionalValue($value);
    }

    /**
     * Разрешает live ResourceSpec.
     *
     * @param CharacterRuleSlice $slice Live rule slice.
     * @param string $ruleCode Resource code.
     *
     * @return ResourceSpec Live spec.
     *
     * @throws CharacterInvalidException Если rule неизвестно или не resource.
     */
    public function resourceSpec(CharacterRuleSlice $slice, string $ruleCode): ResourceSpec
    {
        $rule = $slice->findLive($ruleCode);
        if (!$rule instanceof CharacterResolvedRule || $rule->isSpecBroken()) {
            throw new CharacterInvalidException('Resource rule is unknown');
        }

        $spec = $rule->getSpec();
        if (!$spec instanceof ResourceSpec) {
            throw new CharacterInvalidException('Resource rule is invalid');
        }

        return $spec;
    }

    /**
     * Разбирает ассоциативные части строки и разрешает live spec.
     *
     * @param mixed $row Native row.
     * @param CharacterRuleSlice $slice Live rule slice.
     *
     * @return array{0: string, 1: mixed, 2: int|DimensionalNumber|null, 3: array<int, ResourceLimitBonus>}
     *         Code, current, base, bonuses.
     *
     * @throws CharacterInvalidException При неверной форме или live rule.
     */
    private function parts(mixed $row, CharacterRuleSlice $slice): array
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new CharacterInvalidException('Resource row shape is invalid');
        }

        if (!$this->hasKeys($row, $this->rowKeys($row))) {
            throw new CharacterInvalidException('Resource row shape is invalid');
        }

        $ruleCode = $row['ruleCode'];
        if (!is_string($ruleCode) || $ruleCode === '') {
            throw new CharacterInvalidException('Resource rule code is invalid');
        }

        $this->resourceSpec($slice, $ruleCode);

        return [
            $ruleCode,
            $row['current'],
            $this->base($row['base'] ?? null),
            $this->bonuses($row['bonuses'] ?? []),
        ];
    }

    /**
     * Разрешённые ключи строки по её необязательным частям.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<int, string> Ключи.
     */
    private function rowKeys(array $row): array
    {
        $allowed = ['ruleCode', 'current'];
        if (array_key_exists('base', $row)) {
            $allowed[] = 'base';
        }

        if (array_key_exists('bonuses', $row)) {
            $allowed[] = 'bonuses';
        }

        return $allowed;
    }

    /**
     * Разбирает вклады в лимит.
     *
     * @param mixed $bonuses Native.
     *
     * @return array<int, ResourceLimitBonus> Вклады.
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function bonuses(mixed $bonuses): array
    {
        if (!is_array($bonuses) || !array_is_list($bonuses)) {
            throw new CharacterInvalidException('Resource bonuses are invalid');
        }

        $parsed = [];
        foreach ($bonuses as $bonus) {
            $parsed[] = $this->bonus($bonus);
        }

        return $parsed;
    }

    /**
     * Разбирает один вклад в лимит.
     *
     * @param mixed $bonus Native.
     *
     * @return ResourceLimitBonus Вклад.
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function bonus(mixed $bonus): ResourceLimitBonus
    {
        if (!is_array($bonus) || array_is_list($bonus)) {
            throw new CharacterInvalidException('Resource bonus is invalid');
        }

        $keys = array_keys($bonus);
        sort($keys);
        $source = $bonus['sourceRuleCode'] ?? null;
        $delta = $bonus['delta'] ?? null;
        if (
            $keys !== ['delta', 'sourceRuleCode']
            || ($source !== null && !is_string($source))
            || !is_int($delta)
        ) {
            throw new CharacterInvalidException('Resource bonus is invalid');
        }

        return new ResourceLimitBonus($source, $delta);
    }

    /**
     * Разбирает сохранённый лимит.
     *
     * @param mixed $base Native.
     *
     * @return int|DimensionalNumber|null Лимит.
     *
     * @throws CharacterInvalidException Если форма чужая.
     */
    private function base(mixed $base): int|DimensionalNumber|null
    {
        if ($base === null) {
            return null;
        }

        if (is_int($base)) {
            return $base;
        }

        if (is_array($base) && is_int($base['base'] ?? null) && is_int($base['size'] ?? null)) {
            return new DimensionalNumber($base['base'], $base['size']);
        }

        throw new CharacterInvalidException('Resource base is invalid');
    }

    /**
     * Разбирает scalar native value.
     *
     * @param mixed $value Native value.
     *
     * @return ScalarResourceValue Typed value.
     *
     * @throws CharacterInvalidException При неверной форме.
     */
    private function scalarValue(mixed $value): ScalarResourceValue
    {
        if (!is_int($value) || $value < 0) {
            throw new CharacterInvalidException('Scalar resource value shape is invalid');
        }

        return new ScalarResourceValue($value);
    }

    /**
     * Разбирает dimensional native value.
     *
     * @param mixed $value Native value.
     *
     * @return DimensionalResourceValue Typed value.
     *
     * @throws CharacterInvalidException При неверной форме.
     */
    private function dimensionalValue(mixed $value): DimensionalResourceValue
    {
        if (!is_array($value) || array_is_list($value) || !$this->hasKeys($value, ['base', 'size'])) {
            throw new CharacterInvalidException('Dimensional resource value shape is invalid');
        }

        $base = $value['base'];
        $size = $value['size'];
        if (!is_int($base) || !is_int($size) || $base < 0) {
            throw new CharacterInvalidException('Dimensional resource value is invalid');
        }

        return new DimensionalResourceValue(new DimensionalNumber($base, $size));
    }

    /**
     * Проверяет точный набор associative keys независимо от порядка JSON.
     *
     * @param array<string, mixed> $value Object-like array.
     * @param array<int, string> $expected Expected keys.
     *
     * @return bool true when the key sets are equal.
     */
    private function hasKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }
}
