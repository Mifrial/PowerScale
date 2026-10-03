<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Race\AgeRange;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceAbilityRef;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceCharacteristic;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RacePurchaseLevel;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\SpeciesSpec;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Раса и вид.
 */
final class RaceSpecs
{
    /**
     * Раса.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return RaceSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function race(array $document): RaceSpec
    {
        return new RaceSpec(
            SpecShape::optionalString($document, 'parent_race_code'),
            SpecShape::int($document, 'cost_os'),
            self::characteristics($document),
            self::abilities($document),
        );
    }

    /**
     * Вид.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return SpeciesSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function species(array $document): SpeciesSpec
    {
        return new SpeciesSpec(
            SpecShape::optionalString($document, 'parent_race_code'),
            self::abilities($document),
            self::ages($document),
        );
    }

    /**
     * Характеристики.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, RaceCharacteristic> Строки.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function characteristics(array $document): array
    {
        $rows = [];
        foreach (SpecShape::list($document, 'characteristics') as $row) {
            $rows[] = self::characteristic($row);
        }

        return $rows;
    }

    /**
     * Одна характеристика.
     *
     * @param mixed $row Строка.
     *
     * @return RaceCharacteristic Характеристика.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function characteristic(mixed $row): RaceCharacteristic
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('characteristics');
        }

        return new RaceCharacteristic(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::string($row, 'mode'),
            DimensionalNumbers::characteristic($row, 'base'),
            self::purchase($row),
        );
    }

    /**
     * Лестница закупки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<int, RacePurchaseLevel> Ступени.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function purchase(array $row): array
    {
        $levels = [];
        foreach (SpecShape::list($row, 'purchase') as $level) {
            $levels[] = self::level($level);
        }

        return $levels;
    }

    /**
     * Ступень закупки.
     *
     * @param mixed $level Строка.
     *
     * @return RacePurchaseLevel Ступень.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function level(mixed $level): RacePurchaseLevel
    {
        if (!is_array($level) || array_is_list($level)) {
            throw new RuleSpecShapeException('purchase');
        }

        return new RacePurchaseLevel(SpecShape::int($level, 'cost'), DimensionalNumbers::characteristic($level, 'value'));
    }

    /**
     * Ссылки способностей.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, RaceAbilityRef> Ссылки.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function abilities(array $document): array
    {
        $refs = [];
        foreach (SpecShape::list($document, 'abilities') as $row) {
            $refs[] = self::ability($row);
        }

        return $refs;
    }

    /**
     * Одна ссылка.
     *
     * @param mixed $row Строка.
     *
     * @return RaceAbilityRef Ссылка.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function ability(mixed $row): RaceAbilityRef
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('abilities');
        }

        return new RaceAbilityRef(
            SpecShape::string($row, 'ability_code'),
            SpecShape::bool($row, 'automatic'),
            self::parameters($row),
        );
    }

    /**
     * Параметры ссылки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, DimensionalNumber> Словарь.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function parameters(array $row): array
    {
        $parameters = SpecShape::object($row, 'parameters') ?? [];
        $values = [];
        foreach ($parameters as $code => $value) {
            if (!is_string($code) || !is_array($value) || array_is_list($value)) {
                throw new RuleSpecShapeException('parameters');
            }

            $values[$code] = DimensionalNumbers::pair($value, 'parameters');
        }

        return $values;
    }

    /**
     * Таблица лет.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, AgeRange> Диапазоны.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function ages(array $document): array
    {
        $ranges = [];
        foreach (SpecShape::list($document, 'age_years') as $row) {
            $ranges[] = self::age($row);
        }

        return $ranges;
    }

    /**
     * Один диапазон.
     *
     * @param mixed $row Строка.
     *
     * @return AgeRange Диапазон.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function age(mixed $row): AgeRange
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('age_years');
        }

        return new AgeRange(SpecShape::string($row, 'age'), SpecShape::int($row, 'ageStart'), SpecShape::int($row, 'ageEnd'));
    }
}
