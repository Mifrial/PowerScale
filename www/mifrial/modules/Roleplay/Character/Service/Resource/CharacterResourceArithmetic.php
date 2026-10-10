<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Resource;

use Mifrial\Roleplay\Character\Dto\DimensionalResourceValue;
use Mifrial\Roleplay\Character\Dto\ResourceRow;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ResourceValue;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceLimitChangeGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceLimit;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Выполняет native arithmetic для resource limits и current.
 */
final class CharacterResourceArithmetic
{
    /**
     * Возвращает большую native base.
     *
     * @param ResourceSpec $spec Live resource spec.
     * @param array<int, ResourceGrant|ResourceLimitChangeGrant> $grants Permanent grants.
     *
     * @return int|DimensionalNumber Native base.
     *
     * @throws CharacterInvalidException При mismatch variant.
     */
    public function getBase(
        ResourceSpec $spec,
        array $grants,
    ): int|DimensionalNumber {
        $base = $this->base($spec->getLimit(), $spec->isDimensional());
        foreach ($grants as $grant) {
            if (!$grant instanceof ResourceGrant) {
                continue;
            }

            $candidate = $grant->getLimit();
            $this->assertVariant($candidate, $spec->isDimensional());
            $base = $this->maxBase($base, $candidate);
        }

        return $base;
    }

    /**
     * Применяет scalar modifier без flattening.
     *
     * @param int|DimensionalNumber $base Native base.
     * @param int $delta Scalar delta.
     *
     * @return int|DimensionalNumber Modified base.
     */
    public function applyDelta(int|DimensionalNumber $base, int $delta): int|DimensionalNumber
    {
        if (is_int($base)) {
            return max(0, $base + $delta);
        }

        return new DimensionalNumber(max(0, $base->getBase() + $delta), $base->getSize());
    }

    /**
     * Ограничивает current эффективным лимитом.
     *
     * @param ResourceValue $current Current.
     * @param int|DimensionalNumber $limit Effective limit.
     *
     * @return ResourceValue Clamped current.
     *
     * @throws CharacterInvalidException При mismatch variant.
     */
    public function clamp(ResourceValue $current, int|DimensionalNumber $limit): ResourceValue
    {
        if ($current instanceof ScalarResourceValue && is_int($limit)) {
            return new ScalarResourceValue(min($current->getValue(), $limit));
        }

        if ($current instanceof DimensionalResourceValue && $limit instanceof DimensionalNumber) {
            $value = $current->getValue();
            if (!$this->isGreater($value, $limit)) {
                return $current;
            }

            return $this->value($limit);
        }

        throw new CharacterInvalidException('Resource current and limit variants differ');
    }

    /**
     * Преобразует native limit в typed current.
     *
     * @param int|DimensionalNumber $value Native value.
     *
     * @return ResourceValue Typed value.
     */
    public function value(int|DimensionalNumber $value): ResourceValue
    {
        if (is_int($value)) {
            return new ScalarResourceValue($value);
        }

        return new DimensionalResourceValue($value);
    }

    /**
     * Списывает подготовленные суммы из типизированных строк.
     *
     * @param array<int, ResourceRow> $rows Текущие строки.
     * @param array<int, ResourceSpend> $spends Разрешённые траты.
     *
     * @return array<int, ResourceRow> Строки после атомарной подготовки.
     *
     * @throws CharacterInvalidException Если ресурс, форма или остаток невалидны.
     */
    public function spendRows(array $rows, array $spends): array
    {
        $indexed = $this->indexRows($rows);
        $totals = $this->indexSpends($spends);
        $next = [];
        foreach ($indexed as $code => $row) {
            $amount = $totals[$code] ?? null;
            $next[$code] = $amount === null
                ? $row
                : new ResourceRow($code, $this->subtract($row->getCurrent(), $amount));
        }

        if ($totals !== [] && count(array_intersect_key($totals, $indexed)) !== count($totals)) {
            throw new CharacterInvalidException('Resource spend targets an unknown row');
        }

        return array_values($next);
    }

    /**
     * Выбирает native max двух значений одной формы.
     *
     * @param ResourceValue $left Первое значение.
     * @param ResourceValue $right Второе значение.
     *
     * @return ResourceValue Native max.
     *
     * @throws CharacterInvalidException Если варианты различаются.
     */
    public function maxValue(ResourceValue $left, ResourceValue $right): ResourceValue
    {
        if ($left instanceof ScalarResourceValue && $right instanceof ScalarResourceValue) {
            return new ScalarResourceValue(max($left->getValue(), $right->getValue()));
        }

        if ($left instanceof DimensionalResourceValue && $right instanceof DimensionalResourceValue) {
            $leftNumber = $left->getValue();
            $rightNumber = $right->getValue();
            $size = min($leftNumber->getSize(), $rightNumber->getSize());
            $leftBase = $this->alignedBase($leftNumber, $size);
            $rightBase = $this->alignedBase($rightNumber, $size);

            return $rightBase > $leftBase ? $right : $left;
        }

        throw new CharacterInvalidException('Resource values variants differ');
    }

    /**
     * Преобразует optional limit в ожидаемый variant.
     *
     * @param ResourceLimit|null $limit Rule limit.
     * @param bool $dimensional Expected variant.
     *
     * @return int|DimensionalNumber Native base.
     *
     * @throws CharacterInvalidException При mismatch variant.
     */
    private function base(?ResourceLimit $limit, bool $dimensional): int|DimensionalNumber
    {
        $base = $limit?->getBase() ?? ($dimensional ? new DimensionalNumber(0, 0) : 0);
        $this->assertVariant($base, $dimensional);

        return $base;
    }

    /**
     * Выбирает большую base-кандидатуру.
     *
     * @param int|DimensionalNumber $left Left candidate.
     * @param int|DimensionalNumber $right Right candidate.
     *
     * @return int|DimensionalNumber Greater candidate.
     */
    private function maxBase(int|DimensionalNumber $left, int|DimensionalNumber $right): int|DimensionalNumber
    {
        if (is_int($left) && is_int($right)) {
            return max($left, $right);
        }

        if (!$left instanceof DimensionalNumber || !$right instanceof DimensionalNumber) {
            return $left;
        }

        $size = min($left->getSize(), $right->getSize());
        $rightWins = $this->alignedBase($right, $size) > $this->alignedBase($left, $size);

        return $rightWins ? $right : $left;
    }

    /**
     * Складывает два native значения одной формы.
     *
     * @param ResourceValue $left Первое значение.
     * @param ResourceValue $right Второе значение.
     *
     * @return ResourceValue Сумма.
     *
     * @throws CharacterInvalidException Если варианты различаются.
     */
    private function add(ResourceValue $left, ResourceValue $right): ResourceValue
    {
        if ($left instanceof ScalarResourceValue && $right instanceof ScalarResourceValue) {
            return new ScalarResourceValue($left->getValue() + $right->getValue());
        }

        if ($left instanceof DimensionalResourceValue && $right instanceof DimensionalResourceValue) {
            $size = min($left->getValue()->getSize(), $right->getValue()->getSize());

            return new DimensionalResourceValue(new DimensionalNumber(
                $this->alignedBase($left->getValue(), $size) + $this->alignedBase($right->getValue(), $size),
                $size,
            ));
        }

        throw new CharacterInvalidException('Resource spend variants differ');
    }

    /**
     * Вычитает native spend из current.
     *
     * @param ResourceValue $current Текущее значение.
     * @param ResourceValue $spend Сумма списания.
     *
     * @return ResourceValue Остаток.
     *
     * @throws CharacterInvalidException Если варианты различаются или current недостаточен.
     */
    private function subtract(ResourceValue $current, ResourceValue $spend): ResourceValue
    {
        if ($current instanceof ScalarResourceValue && $spend instanceof ScalarResourceValue) {
            if ($current->getValue() < $spend->getValue()) {
                throw new CharacterInvalidException('Resource current is insufficient');
            }

            return new ScalarResourceValue($current->getValue() - $spend->getValue());
        }

        if ($current instanceof DimensionalResourceValue && $spend instanceof DimensionalResourceValue) {
            $currentValue = $current->getValue();
            $spendValue = $spend->getValue();
            $size = min($currentValue->getSize(), $spendValue->getSize());
            $currentBase = $this->alignedBase($currentValue, $size);
            $spendBase = $this->alignedBase($spendValue, $size);
            if ($currentBase < $spendBase) {
                throw new CharacterInvalidException('Resource current is insufficient');
            }

            return new DimensionalResourceValue(new DimensionalNumber($currentBase - $spendBase, $size));
        }

        throw new CharacterInvalidException('Resource spend variants differ');
    }

    /**
     * Индексирует текущие строки ресурсов.
     *
     * @param array<int, ResourceRow> $rows Строки.
     *
     * @return array<string, ResourceRow> Строки по коду.
     *
     * @throws CharacterInvalidException Если строка дублируется или невалидна.
     */
    private function indexRows(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!$row instanceof ResourceRow || isset($indexed[$row->getRuleCode()])) {
                throw new CharacterInvalidException('Resource rows are invalid');
            }

            $indexed[$row->getRuleCode()] = $row;
        }

        return $indexed;
    }

    /**
     * Объединяет компоненты одного ресурса.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<string, ResourceValue> Суммы по коду.
     *
     * @throws CharacterInvalidException Если трата невалидна.
     */
    private function indexSpends(array $spends): array
    {
        $indexed = [];
        foreach ($spends as $spend) {
            if (!$spend instanceof ResourceSpend) {
                throw new CharacterInvalidException('Resource spend is invalid');
            }

            $code = $spend->getResourceCode();
            $indexed[$code] = isset($indexed[$code])
                ? $this->add($indexed[$code], $spend->getAmount())
                : $spend->getAmount();
        }

        return $indexed;
    }

    /**
     * Выравнивает native dimensional base к общему размеру.
     *
     * @param DimensionalNumber $number Значение.
     * @param int $size Общий размер.
     *
     * @return int Выравненная база.
     */
    private function alignedBase(DimensionalNumber $number, int $size): int
    {
        $base = $number->getBase();
        for ($step = $number->getSize() - $size; $step > 0; $step--) {
            $base *= 2;
        }

        return $base;
    }

    /**
     * Сравнивает размерные значения без flattening.
     *
     * @param DimensionalNumber $left Current.
     * @param DimensionalNumber $right Limit.
     *
     * @return bool true, если current выше limit.
     */
    private function isGreater(DimensionalNumber $left, DimensionalNumber $right): bool
    {
        $size = min($left->getSize(), $right->getSize());

        return $this->alignedBase($left, $size) > $this->alignedBase($right, $size);
    }

    /**
     * Проверяет native variant.
     *
     * @param int|DimensionalNumber $value Candidate.
     * @param bool $dimensional Expected variant.
     *
     * @return void
     *
     * @throws CharacterInvalidException При mismatch variant.
     */
    private function assertVariant(int|DimensionalNumber $value, bool $dimensional): void
    {
        if ($dimensional !== ($value instanceof DimensionalNumber)) {
            throw new CharacterInvalidException('Resource limit variant is invalid');
        }
    }
}
