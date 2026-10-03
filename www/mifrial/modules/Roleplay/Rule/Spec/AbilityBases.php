<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Общие поля способности.
 */
final class AbilityBases
{
    /**
     * Читает базу. Вложенные зоны и требования сохраняются как разобранные списки.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return AbilityBase База.
     *
     * @throws RuleSpecShapeException Если grants чужой формы.
     */
    public static function read(array $document): AbilityBase
    {
        return new AbilityBase(
            AbilityZones::read($document),
            AbilityRequirements::read($document),
            AbilityGrants::blocks($document),
            ActionEffects::list($document),
            AbilityParameters::read($document),
            SpecShape::optionalString($document, 'parent_ability_code'),
            SpecShape::optionalString($document, 'attack_mode'),
            SpecShape::optionalInt($document, 'min_total_action_cost'),
            SpecShape::optionalInt($document, 'strike_count'),
            SpecShape::bool($document, 'distinct_weapons'),
            SpecShape::bool($document, 'same_weapon'),
            SpecShape::optionalInt($document, 'min_weapons'),
            SpecShape::optionalInt($document, 'max_weapons'),
            SpecShape::bool($document, 'lift_parent_max_weapons'),
            SpecShape::optionalInt($document, 'max_targets'),
            SpecShape::optionalString($document, 'combat_action'),
            SpecShape::bool($document, 'peak_concentration'),
            SpecShape::bool($document, 'will_focus'),
            SpecShape::bool($document, 'long_tension'),
            SpecShape::bool($document, 'multiple'),
            SpecShape::optionalString($document, 'weapon_item_code'),
            SpecShape::optionalString($document, 'group_code'),
            SpecShape::optionalString($document, 'domain_ref'),
            SpecShape::optionalString($document, 'knowledge_template_field'),
            SpecShape::optionalString($document, 'parent_knowledge_field'),
            SpecShape::optionalInt($document, 'movement_step_size_delta'),
            AbilityCombat::hit($document),
            AbilityCombat::push($document),
            AbilityCombat::spellUpgrade($document),
            AbilityCombat::saturation($document),
            AbilityCombat::nextDifficulty($document),
            AbilityCombat::touch($document),
            AbilityCombat::cover($document),
            AbilityCombat::knownDefense($document),
            AbilityCombat::strikeUpgrade($document),
            self::aggregate($document),
            self::derivedLevel($document),
        );
    }

    /**
     * Агрегат. Нет ключа — null.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return \Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityAggregate|null Агрегат или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function aggregate(array $document): ?\Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityAggregate
    {
        $row = SpecShape::object($document, 'aggregate');
        if ($row === null) {
            return null;
        }

        return new \Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityAggregate(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::string($row, 'method_keyword'),
            SpecShape::intList($row, 'levels'),
        );
    }

    /**
     * Производный уровень. Нет ключа — null.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return \Mifrial\Roleplay\Rule\Dto\Spec\Ability\DerivedLevel|null Уровень или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function derivedLevel(array $document): ?\Mifrial\Roleplay\Rule\Dto\Spec\Ability\DerivedLevel
    {
        $row = SpecShape::object($document, 'derived_level');
        if ($row === null) {
            return null;
        }

        return new \Mifrial\Roleplay\Rule\Dto\Spec\Ability\DerivedLevel(
            SpecShape::string($row, 'source_keyword'),
            SpecShape::intList($row, 'thresholds'),
        );
    }
}
