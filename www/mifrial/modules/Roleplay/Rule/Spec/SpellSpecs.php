<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellCharge;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamageStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDuration;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellValue;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Тело заклинания.
 */
final class SpellSpecs
{
    /**
     * Читает spell. Нет длительности — instant.
     *
     * @param array<string|int, mixed> $document Документ способности.
     *
     * @return SpellSpec Тело.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): SpellSpec
    {
        $spell = SpecShape::object($document, 'spell') ?? [];

        return new SpellSpec(
            self::value($spell, 'power'),
            self::value($spell, 'control'),
            self::duration($spell),
            SpecShape::optionalString($spell, 'targeting'),
            self::damage($spell),
            self::charge($spell),
        );
    }

    /**
     * Мощь или контроль. Нет ключа — нули.
     *
     * @param array<string, mixed> $spell Тело.
     * @param string $key Ключ.
     *
     * @return SpellValue Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function value(array $spell, string $key): SpellValue
    {
        $value = SpecShape::object($spell, $key);
        if ($value === null) {
            return new SpellValue(new DimensionalNumber(0, 0), null);
        }

        if (($value['type'] ?? null) === 'parameter') {
            return new SpellValue(null, SpecShape::string($value, 'parameter_code'));
        }

        return new SpellValue(DimensionalNumbers::pair($value, $key), null);
    }

    /**
     * Длительность.
     *
     * @param array<string, mixed> $spell Тело.
     *
     * @return SpellDuration Длительность.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function duration(array $spell): SpellDuration
    {
        $duration = SpecShape::object($spell, 'duration');
        if ($duration === null) {
            return new SpellDuration('instant', null, null, null, null);
        }

        $limit = SpecShape::object($duration, 'limit');

        return new SpellDuration(
            SpecShape::string($duration, 'type'),
            self::intOrNumber($limit, 'value'),
            $limit === null ? null : SpecShape::string($limit, 'unit'),
            self::intOrNumber($duration, 'action_cost'),
            array_key_exists('power', $duration) ? self::value($duration, 'power') : null,
        );
    }

    /**
     * Урон.
     *
     * @param array<string, mixed> $spell Тело.
     *
     * @return SpellDamage|null Урон или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function damage(array $spell): ?SpellDamage
    {
        $damage = SpecShape::object($spell, 'damage');
        if ($damage === null) {
            return null;
        }

        $falloff = SpecShape::object($damage, 'falloff');

        return new SpellDamage(
            SpecShape::string($damage, 'damage_type_code'),
            SpecShape::string($damage, 'experience_keyword_code'),
            self::steps($damage),
            $falloff === null ? null : SpecShape::int($falloff, 'free_ipari'),
            $falloff === null ? null : SpecShape::int($falloff, 'size_per_extra_ipari'),
            $falloff === null ? null : DimensionalNumbers::required($falloff, 'min'),
        );
    }

    /**
     * Ступени урона.
     *
     * @param array<string, mixed> $damage Урон.
     *
     * @return array<int, SpellDamageStep> Ступени.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function steps(array $damage): array
    {
        $steps = [];
        foreach (SpecShape::list($damage, 'steps') as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new RuleSpecShapeException('steps');
            }

            $steps[] = new SpellDamageStep(SpecShape::int($row, 'min_experience'), SpecShape::int($row, 'modify'));
        }

        return $steps;
    }

    /**
     * Заряд.
     *
     * @param array<string, mixed> $spell Тело.
     *
     * @return SpellCharge|null Заряд или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function charge(array $spell): ?SpellCharge
    {
        $charge = SpecShape::object($spell, 'charge');
        if ($charge === null) {
            return null;
        }

        $spend = SpecShape::object($charge, 'spend') ?? [];

        return new SpellCharge(
            SpecShape::string($charge, 'state_code'),
            SpecShape::int($charge, 'grant'),
            SpecShape::int($charge, 'default_cap'),
            SpecShape::int($spend, 'action_points'),
            SpecShape::int($spend, 'amount'),
            SpecShape::string($spend, 'keyword_code'),
            SpecShape::stringList($spend, 'excluded_durations'),
            SpecShape::string($charge, 'creation_max'),
        );
    }

    /**
     * Целое или размерное число.
     *
     * @param array<string, mixed>|null $document Документ.
     * @param string $key Ключ.
     *
     * @return int|DimensionalNumber|null Значение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function intOrNumber(?array $document, string $key): int|DimensionalNumber|null
    {
        if ($document === null || !array_key_exists($key, $document) || $document[$key] === null) {
            return null;
        }

        $value = $document[$key];
        if (is_int($value)) {
            return $value;
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new RuleSpecShapeException($key);
        }

        return DimensionalNumbers::pair($value, $key);
    }
}
