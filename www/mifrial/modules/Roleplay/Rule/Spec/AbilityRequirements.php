<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\AndRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CharacteristicValueRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CurrentSpeedRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityKeywordRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasKeywordRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasMagicPathRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MagicPathExperienceRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MinWeaponMasteryRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\OrRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\ResourceLimitRequirement;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Требования способности.
 */
final class AbilityRequirements
{
    /**
     * Блоки по уровням.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, array{level: int, requirements: array<int, AbilityRequirement>}> Блоки.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): array
    {
        $blocks = [];
        foreach (SpecShape::list($document, 'requirements') as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new RuleSpecShapeException('requirements');
            }

            $items = [];
            foreach (SpecShape::list($row, 'requirements') as $item) {
                $items[] = self::one($item);
            }

            $blocks[] = ['level' => SpecShape::int($row, 'level'), 'requirements' => $items];
        }

        return $blocks;
    }

    /**
     * Одно требование.
     *
     * @param mixed $row Строка.
     *
     * @return AbilityRequirement Требование.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function one(mixed $row): AbilityRequirement
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('requirements');
        }

        return self::byType(SpecShape::string($row, 'type'), $row);
    }

    /**
     * Ветка требования.
     *
     * @param string $type Вид.
     * @param array<string, mixed> $row Строка.
     *
     * @return AbilityRequirement Требование.
     *
     * @throws RuleSpecShapeException Если вид неизвестен.
     */
    private static function byType(string $type, array $row): AbilityRequirement
    {
        return match ($type) {
            'has_ability' => new HasAbilityRequirement(
                SpecShape::string($row, 'ability_code'),
                SpecShape::optionalInt($row, 'min_level'),
            ),
            'has_ability_keyword' => new HasAbilityKeywordRequirement(
                SpecShape::string($row, 'keyword_code'),
                SpecShape::int($row, 'min_count'),
                SpecShape::stringList($row, 'exclude_keyword_codes'),
            ),
            'has_keyword' => new HasKeywordRequirement(SpecShape::string($row, 'keyword_code')),
            'min_weapon_mastery' => new MinWeaponMasteryRequirement(
                SpecShape::string($row, 'keyword_code'),
                SpecShape::int($row, 'min_level'),
            ),
            'characteristic_value' => new CharacteristicValueRequirement(
                SpecShape::string($row, 'characteristic_code'),
                DimensionalNumbers::required($row, 'min'),
            ),
            'resource_limit' => new ResourceLimitRequirement(SpecShape::string($row, 'resource_code'), self::limit($row)),
            'has_magic_path' => new HasMagicPathRequirement(SpecShape::string($row, 'path_code')),
            'magic_path_experience' => new MagicPathExperienceRequirement(
                SpecShape::string($row, 'path_code'),
                SpecShape::int($row, 'min'),
            ),
            'current_speed' => new CurrentSpeedRequirement(
                SpecShape::string($row, 'axis'),
                SpecShape::string($row, 'direction'),
                SpecShape::int($row, 'min_steps_per_action_point'),
            ),
            'and' => new AndRequirement(self::children($row)),
            'or' => new OrRequirement(self::children($row)),
            default => throw new RuleSpecShapeException('requirements'),
        };
    }

    /**
     * Дети and/or.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<int, AbilityRequirement> Дети.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function children(array $row): array
    {
        $children = [];
        foreach (SpecShape::list($row, 'requirements') as $child) {
            $children[] = self::one($child);
        }

        return $children;
    }

    /**
     * Минимум лимита: целое или размерное число.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int|DimensionalNumber|null Значение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function limit(array $row): int|DimensionalNumber|null
    {
        if (!array_key_exists('min', $row) || $row['min'] === null) {
            return null;
        }

        $min = $row['min'];
        if (is_int($min)) {
            return $min;
        }

        if (!is_array($min) || array_is_list($min)) {
            throw new RuleSpecShapeException('min');
        }

        return DimensionalNumbers::pair($min, 'min');
    }
}
