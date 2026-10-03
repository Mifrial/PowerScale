<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityParameter;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ParameterLink;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Параметры способности.
 */
final class AbilityParameters
{
    /**
     * Список параметров.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, AbilityParameter> Параметры.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): array
    {
        $parameters = [];
        foreach (SpecShape::list($document, 'parameters') as $row) {
            $parameters[] = self::one($row);
        }

        return $parameters;
    }

    /**
     * Один параметр.
     *
     * @param mixed $row Строка.
     *
     * @return AbilityParameter Параметр.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function one(mixed $row): AbilityParameter
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('parameters');
        }

        $kind = SpecShape::string($row, 'kind');

        return new AbilityParameter(
            SpecShape::string($row, 'code'),
            SpecShape::string($row, 'label'),
            SpecShape::optionalString($row, 'description'),
            SpecShape::string($row, 'resolution'),
            $kind,
            self::bound($row, 'default', $kind),
            self::optionalBound($row, 'min', $kind),
            self::optionalBound($row, 'max', $kind),
            self::linked($row),
        );
    }

    /**
     * Граница.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $kind Вид.
     *
     * @return int|DimensionalNumber Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function bound(array $row, string $key, string $kind): int|DimensionalNumber
    {
        if ($kind === 'dimensional') {
            return DimensionalNumbers::required($row, $key);
        }

        return SpecShape::int($row, $key);
    }

    /**
     * Необязательная граница.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Ключ.
     * @param string $kind Вид.
     *
     * @return int|DimensionalNumber|null Значение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function optionalBound(array $row, string $key, string $kind): int|DimensionalNumber|null
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        return self::bound($row, $key, $kind);
    }

    /**
     * Связь. Нет ключа — null.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return ParameterLink|null Связь или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function linked(array $row): ?ParameterLink
    {
        $linked = SpecShape::object($row, 'linked');
        if ($linked === null) {
            return null;
        }

        return new ParameterLink(
            SpecShape::string($linked, 'ability_code'),
            SpecShape::string($linked, 'parameter_code'),
            SpecShape::int($linked, 'max_delta'),
        );
    }
}
