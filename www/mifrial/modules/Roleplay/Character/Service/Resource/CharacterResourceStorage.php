<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Resource;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\DimensionalResourceValue;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ResourceValue;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разбирает и сериализует native-границу хранения ресурсов.
 */
final class CharacterResourceStorage
{
    private readonly CharacterResourceArithmetic $arithmetic;

    /**
     * Создаёт границу хранения ресурсов.
     *
     * @return void
     */
    public function __construct()
    {
        $this->arithmetic = new CharacterResourceArithmetic();
    }

    /**
     * Разбирает сохранённые строки по live-правилам.
     *
     * @param array<mixed> $rows Native rows.
     * @param CharacterRuleSlice $slice Live rule slice.
     *
     * @return array<int, ResourceRow> Validated rows.
     *
     * @throws CharacterInvalidException If the storage shape is invalid.
     */
    public function parseRows(array $rows, CharacterRuleSlice $slice): array
    {
        if (!array_is_list($rows)) {
            throw new CharacterInvalidException('Resource rows must be a list');
        }

        $parsed = [];
        $seen = [];
        foreach ($rows as $row) {
            $resourceRow = $this->parseRow($row, $slice);
            $code = $resourceRow->getRuleCode();
            if (isset($seen[$code])) {
                throw new CharacterInvalidException('Resource row is duplicated');
            }

            $seen[$code] = true;
            $parsed[] = $resourceRow;
        }

        return $parsed;
    }

    /**
     * Сериализует проверенные строки в native-форму.
     *
     * @param array<int, ResourceRow> $rows Validated rows.
     *
     * @return array<int, array{ruleCode: string, current: int|array{base: int, size: int}}> Native rows.
     *
     * @throws CharacterInvalidException If a row has an invalid runtime type.
     */
    public function serializeRows(array $rows): array
    {
        $serialized = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!$row instanceof ResourceRow) {
                throw new CharacterInvalidException('Resource row is invalid');
            }

            $code = $row->getRuleCode();
            if (isset($seen[$code])) {
                throw new CharacterInvalidException('Resource row is duplicated');
            }

            $seen[$code] = true;
            $serialized[] = [
                'ruleCode' => $code,
                'current' => $row->getCurrent()->toNative(),
            ];
        }

        return $serialized;
    }

    /**
     * Готовит атомарное списание по native-строкам.
     *
     * @param array<int, ResourceRow> $rows Проверенные строки.
     * @param CharacterRuleSlice $slice Live rule slice.
     * @param array<int, ResourceSpend> $spends Server-resolved spends.
     *
     * @return array<int, ResourceRow> Rows after spend.
     *
     * @throws CharacterInvalidException If a resource or amount is invalid.
     */
    public function spendRows(
        array $rows,
        CharacterRuleSlice $slice,
        array $spends,
    ): array {
        $validated = [];
        foreach ($spends as $spend) {
            if (!$spend instanceof ResourceSpend) {
                throw new CharacterInvalidException('Resource spend is invalid');
            }

            $spec = $this->spec($slice, $spend->getResourceCode());
            $amount = $this->parseValue($spend->getAmount()->toNative(), $spec);
            $validated[] = new ResourceSpend($spend->getResourceCode(), $amount);
        }

        return $this->arithmetic->spendRows($rows, $validated);
    }

    /**
     * Проверяет достаточность всех подготовленных трат без записи.
     *
     * @param array<int, ResourceRow> $rows Проверенные строки.
     * @param CharacterRuleSlice $slice Live rule slice.
     * @param array<int, ResourceSpend> $spends Server-resolved spends.
     *
     * @return bool true, если все траты допустимы по current.
     *
     * @throws CharacterInvalidException Если форма невалидна.
     */
    public function canSpendRows(
        array $rows,
        CharacterRuleSlice $slice,
        array $spends,
    ): bool {
        try {
            $this->spendRows($rows, $slice, $spends);
        } catch (CharacterInvalidException $exception) {
            if ($exception->getMessage() === 'Resource current is insufficient') {
                return false;
            }

            throw $exception;
        }

        return true;
    }

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
    private function parseRow(mixed $row, CharacterRuleSlice $slice): ResourceRow
    {
        [$ruleCode, $value] = $this->rowParts($row, $slice);

        return new ResourceRow($ruleCode, $this->parseValue($value, $this->spec($slice, $ruleCode)));
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
    private function parseValue(mixed $value, ResourceSpec $spec): ResourceValue
    {
        if (!$spec->isDimensional()) {
            return $this->scalarValue($value);
        }

        return $this->dimensionalValue($value);
    }

    /**
     * Разбирает ассоциативные части строки и разрешает live spec.
     *
     * @param mixed $row Native row.
     * @param CharacterRuleSlice $slice Live rule slice.
     *
     * @return array{0: string, 1: mixed} Code and current.
     *
     * @throws CharacterInvalidException При неверной форме или live rule.
     */
    private function rowParts(mixed $row, CharacterRuleSlice $slice): array
    {
        if (!is_array($row) || array_is_list($row) || !$this->hasKeys($row, ['ruleCode', 'current'])) {
            throw new CharacterInvalidException('Resource row shape is invalid');
        }

        $ruleCode = $row['ruleCode'];
        if (!is_string($ruleCode) || $ruleCode === '') {
            throw new CharacterInvalidException('Resource rule code is invalid');
        }

        $this->spec($slice, $ruleCode);

        return [$ruleCode, $row['current']];
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
    private function spec(CharacterRuleSlice $slice, string $ruleCode): ResourceSpec
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
