<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CounterOp;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CounterThreshold;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeModifier;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Счётчики процесса: пороги, изменения и формулы пакета удара.
 */
final class ProcessCounters
{
    /**
     * Пакет удара: advantage и extra_successes.
     *
     * @param array<string, mixed> $document Документ.
     * @param string $key Ключ пакета.
     *
     * @return StrikeModifier|null Пакет или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function readStrikeModifier(array $document, string $key): ?StrikeModifier
    {
        $row = SpecShape::object($document, $key);
        if ($row === null) {
            return null;
        }

        return new StrikeModifier(
            self::formula($row, 'advantage'),
            self::formula($row, 'extra_successes'),
        );
    }

    /**
     * Пороги счётчиков. Пустой список — без порога.
     *
     * @param array<string, mixed> $document Документ.
     * @param string $key Ключ списка.
     * @param array<int, string>|null $counterCodes Объявленные счётчики или null без проверки.
     *
     * @return array<int, CounterThreshold> Пороги.
     *
     * @throws RuleSpecShapeException Если форма чужая или счётчик не объявлен.
     */
    public static function readThresholds(array $document, string $key, ?array $counterCodes = null): array
    {
        $thresholds = [];
        foreach (SpecShape::list($document, $key) as $row) {
            $row = SpecShape::requireMap($row, $key);
            $code = ProcessShape::requiredString($row, 'counter_code', $key);
            if ($counterCodes !== null && !in_array($code, $counterCodes, true)) {
                throw new RuleSpecShapeException($key);
            }

            $thresholds[] = new CounterThreshold($code, ProcessShape::requiredInt($row, 'min', $key));
        }

        return $thresholds;
    }

    /**
     * Изменения счётчиков: delta или set, ровно одно.
     *
     * @param array<string, mixed> $document Документ.
     * @param string $key Ключ списка.
     * @param array<int, string> $counterCodes Объявленные счётчики.
     *
     * @return array<int, CounterOp> Операции.
     *
     * @throws RuleSpecShapeException Если форма чужая или счётчик не объявлен.
     */
    public static function counterOps(array $document, string $key, array $counterCodes): array
    {
        $ops = [];
        foreach (SpecShape::list($document, $key) as $row) {
            $row = SpecShape::requireMap($row, $key);
            $code = ProcessShape::requiredString($row, 'counter_code', $key);
            if (!in_array($code, $counterCodes, true)) {
                throw new RuleSpecShapeException($key);
            }

            $delta = SpecShape::optionalInt($row, 'delta');
            $set = SpecShape::optionalInt($row, 'set');
            if (($delta === null) === ($set === null)) {
                throw new RuleSpecShapeException($key);
            }

            $ops[] = $delta !== null ? CounterOp::delta($code, $delta) : CounterOp::set($code, (int) $set);
        }

        return $ops;
    }

    /**
     * Формула процесса.
     *
     * @param array<string, mixed> $row Объект.
     * @param string $key Ключ формулы.
     *
     * @return ProcessFormula|null Формула или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function formula(array $row, string $key): ?ProcessFormula
    {
        $formula = SpecShape::object($row, $key);
        if ($formula === null) {
            return null;
        }

        $type = SpecShape::string($formula, 'type');
        if ($type === ProcessFormula::TYPE_FIXED) {
            return ProcessFormula::fixed(ProcessShape::requiredInt($formula, 'value', $key));
        }

        if ($type !== ProcessFormula::TYPE_COUNTER) {
            throw new RuleSpecShapeException($key);
        }

        $divisor = SpecShape::optionalInt($formula, 'divisor') ?? 1;
        if ($divisor <= 0) {
            throw new RuleSpecShapeException($key);
        }

        return ProcessFormula::counter(
            ProcessShape::requiredString($formula, 'counter_code', $key),
            SpecShape::optionalInt($formula, 'factor') ?? 1,
            $divisor,
            SpecShape::optionalInt($formula, 'min_value'),
            SpecShape::optionalInt($formula, 'max_value'),
        );
    }
}
