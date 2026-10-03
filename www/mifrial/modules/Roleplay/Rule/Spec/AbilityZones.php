<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ArrayZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\AutomaticZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterSumTablesZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterTableZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ParameterZone;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone\ProgressionZone;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Зоны цены способности.
 */
final class AbilityZones
{
    /**
     * Словарь зон.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<string, AbilityZone> Зоны.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): array
    {
        $zones = SpecShape::object($document, 'zones') ?? [];
        $prices = [];
        foreach ($zones as $code => $row) {
            if (!is_string($code) || !is_array($row) || array_is_list($row)) {
                throw new RuleSpecShapeException('zones');
            }

            $prices[$code] = self::zone(SpecShape::string($row, 'type'), $row);
        }

        return $prices;
    }

    /**
     * Одна зона.
     *
     * @param string $type Вид цены.
     * @param array<string, mixed> $row Строка.
     *
     * @return AbilityZone Зона.
     *
     * @throws RuleSpecShapeException Если вид неизвестен.
     */
    private static function zone(string $type, array $row): AbilityZone
    {
        return match ($type) {
            'array' => new ArrayZone(SpecShape::intList($row, 'levels_cost')),
            'progression' => new ProgressionZone(
                SpecShape::int($row, 'max_level'),
                SpecShape::int($row, 'base_cost'),
                SpecShape::int($row, 'step'),
            ),
            'automatic' => new AutomaticZone(),
            'parameter' => new ParameterZone(SpecShape::string($row, 'parameter_code'), SpecShape::int($row, 'per_unit')),
            'parameter_table' => new ParameterTableZone(SpecShape::string($row, 'parameter_code'), self::table($row)),
            'parameter_sum_tables' => new ParameterSumTablesZone(SpecShape::int($row, 'max_level'), self::tables($row)),
            default => throw new RuleSpecShapeException('zones'),
        };
    }

    /**
     * Таблица значений.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, int> Словарь.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function table(array $row): array
    {
        $table = SpecShape::object($row, 'table') ?? [];
        $values = [];
        foreach ($table as $key => $cost) {
            if (!is_string($key) || !is_int($cost)) {
                throw new RuleSpecShapeException('table');
            }

            $values[$key] = $cost;
        }

        return $values;
    }

    /**
     * Таблицы нескольких параметров.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, array<string, int>> Словарь.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function tables(array $row): array
    {
        $source = SpecShape::object($row, 'tables') ?? [];
        $tables = [];
        foreach ($source as $code => $table) {
            if (!is_string($code) || !is_array($table)) {
                throw new RuleSpecShapeException('tables');
            }

            $tables[$code] = self::table(['table' => $table]);
        }

        return $tables;
    }
}
