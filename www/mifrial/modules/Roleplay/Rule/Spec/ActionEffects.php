<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AttackScope;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\AfterActionUntilResourceSpentCheckModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ApplyStateEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\AttackSrFromPreviousEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackAccuracyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicFromSuccessRatingEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackCharacteristicModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackDodgeSoakEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionAttackReachEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionCheckModifierEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionDurabilityShaveEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionRollFaceRemapEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\CurrentActionRollScoreAdjustEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\LastStrikeSnapshotEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackAccuracyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackCostEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackDodgeSoakFromReactionEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextActionAttackScoreAdjustEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\NextSpellCastDifficultyEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\OptionalAfterStrikeCheckEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\PreparedDefenseCounterEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\RequirePreviousAttackEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\RequirePreviousStrikeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\StrikeSnapshotHit;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Эффекты действия.
 */
final class ActionEffects
{
    /**
     * Список эффектов.
     *
     * @param array<string|int, mixed> $document Документ.
     * @param string $key Ключ.
     *
     * @return array<int, ActionEffect> Эффекты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function list(array $document, string $key = 'action_effects'): array
    {
        $effects = [];
        foreach (SpecShape::list($document, $key) as $row) {
            $effects[] = self::one($row, $key);
        }

        return $effects;
    }

    /**
     * Один эффект.
     *
     * @param mixed $row Строка.
     * @param string $key Ключ списка.
     *
     * @return ActionEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function one(mixed $row, string $key): ActionEffect
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException($key);
        }

        return self::byType(SpecShape::string($row, 'type'), $row, $key);
    }

    /**
     * Ветки атаки текущего действия.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     * @param string $key Ключ списка.
     *
     * @return ActionEffect Эффект.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    private static function byType(string $type, array $row, string $key): ActionEffect
    {
        $attack = self::attack($type, $row);
        if ($attack !== null) {
            return $attack;
        }

        $follow = self::follow($type, $row);
        if ($follow !== null) {
            return $follow;
        }

        return self::rest($type, $row, $key);
    }

    /**
     * Эффекты текущего удара.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     *
     * @return ActionEffect|null Эффект или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function attack(string $type, array $row): ?ActionEffect
    {
        $scope = self::scope($row);

        return match ($type) {
            'current_action_attack_accuracy' => new CurrentActionAttackAccuracyEffect(SpecShape::int($row, 'delta'), $scope),
            'current_action_attack_reach' => new CurrentActionAttackReachEffect(self::fraction($row), $scope),
            'current_action_attack_characteristic_modifier' => new CurrentActionAttackCharacteristicModifierEffect(
                SpecShape::int($row, 'delta'),
                $scope,
                SpecShape::optionalInt($row, 'min_occupy_hands'),
                SpecShape::stringList($row, 'damage_type_codes'),
            ),
            'current_action_attack_characteristic_from_success_rating' => new CurrentActionAttackCharacteristicFromSuccessRatingEffect(
                SpecShape::int($row, 'floor_div'),
                SpecShape::int($row, 'cap'),
                $scope,
            ),
            'current_action_attack_dodge_soak' => new CurrentActionAttackDodgeSoakEffect(
                SpecShape::int($row, 'size_delta'),
                SpecShape::optionalInt($row, 'ignore_at_sr'),
                $scope,
            ),
            'current_action_roll_score_adjust' => new CurrentActionRollScoreAdjustEffect(
                SpecShape::int($row, 'oneDelta'),
                SpecShape::int($row, 'faceDelta'),
                $scope,
            ),
            'current_action_roll_face_remap' => new CurrentActionRollFaceRemapEffect(
                SpecShape::int($row, 'from'),
                SpecShape::int($row, 'to'),
                $scope,
            ),
            'current_action_durability_shave' => new CurrentActionDurabilityShaveEffect(
                SpecShape::bool($row, 'short_extra_on_first_one'),
                $scope,
                SpecShape::stringList($row, 'damage_type_codes'),
            ),
            default => null,
        };
    }

    /**
     * Эффекты следующего действия.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     *
     * @return ActionEffect|null Эффект или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function follow(string $type, array $row): ?ActionEffect
    {
        return match ($type) {
            'current_action_check_modifier' => new CurrentActionCheckModifierEffect(
                SpecShape::stringList($row, 'check_codes'),
                SpecShape::int($row, 'delta'),
                SpecShape::optionalString($row, 'source_code'),
            ),
            'next_action_attack_cost' => new NextActionAttackCostEffect(
                SpecShape::string($row, 'resource_code'),
                SpecShape::int($row, 'delta'),
            ),
            'next_action_attack_accuracy' => new NextActionAttackAccuracyEffect(
                SpecShape::int($row, 'delta'),
                SpecShape::bool($row, 'same_target'),
                SpecShape::optionalString($row, 'targetKey'),
                self::scope($row),
            ),
            'next_action_attack_dodge_soak_from_reaction' => new NextActionAttackDodgeSoakFromReactionEffect(
                SpecShape::optionalInt($row, 'max_total_action_cost'),
                self::scope($row),
            ),
            'after_action_until_resource_spent_check_modifier' => new AfterActionUntilResourceSpentCheckModifierEffect(
                SpecShape::string($row, 'resource_code'),
                SpecShape::int($row, 'amount'),
                SpecShape::stringList($row, 'check_codes'),
                SpecShape::int($row, 'delta'),
                SpecShape::optionalString($row, 'source_code'),
                SpecShape::optionalString($row, 'applies_to'),
            ),
            'next_action_attack_score_adjust' => new NextActionAttackScoreAdjustEffect(
                SpecShape::int($row, 'oneDelta'),
                SpecShape::int($row, 'faceDelta'),
                SpecShape::bool($row, 'same_target'),
                SpecShape::optionalString($row, 'targetKey'),
            ),
            'next_spell_cast_difficulty' => new NextSpellCastDifficultyEffect(
                SpecShape::int($row, 'delta'),
                SpecShape::optionalString($row, 'source_code'),
                SpecShape::int($row, 'max_total_action_cost'),
            ),
            default => null,
        };
    }

    /**
     * Условия и состояние.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Строка.
     * @param string $key Ключ списка.
     *
     * @return ActionEffect Эффект.
     *
     * @throws RuleSpecShapeException Если тип неизвестен.
     */
    private static function rest(string $type, array $row, string $key): ActionEffect
    {
        return match ($type) {
            'require_previous_strike' => new RequirePreviousStrikeEffect(
                SpecShape::int($row, 'min_sr'),
                SpecShape::string($row, 'not_kind'),
                SpecShape::bool($row, 'same_target'),
            ),
            'require_previous_attack' => new RequirePreviousAttackEffect(
                SpecShape::bool($row, 'same_target'),
                SpecShape::bool($row, 'all_damaged'),
                SpecShape::bool($row, 'single_strike'),
            ),
            'attack_sr_from_previous' => new AttackSrFromPreviousEffect(
                SpecShape::int($row, 'floor_div'),
                SpecShape::string($row, 'cap'),
            ),
            'last_strike_snapshot' => new LastStrikeSnapshotEffect(SpecShape::string($row, 'kind'), self::hits($row)),
            'prepared_defense_counter' => new PreparedDefenseCounterEffect(
                SpecShape::string($row, 'targetKey'),
                SpecShape::string($row, 'reaction'),
            ),
            'apply_state' => new ApplyStateEffect(SpecShape::string($row, 'state_code'), self::amount($row)),
            'optional_after_strike_check' => self::afterStrike($row),
            default => throw new RuleSpecShapeException($key),
        };
    }

    /**
     * Проверка после удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return OptionalAfterStrikeCheckEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function afterStrike(array $row): OptionalAfterStrikeCheckEffect
    {
        $damage = SpecShape::object($row, 'self_damage') ?? [];

        return new OptionalAfterStrikeCheckEffect(
            SpecShape::string($row, 'check_code'),
            SpecShape::int($row, 'difficulty'),
            SpecShape::bool($row, 'skip_parent_pending'),
            new SelfDamage(
                SpecShape::int($damage, 'size_delta'),
                SpecShape::string($damage, 'damage_type_code'),
                SpecShape::bool($damage, 'internal'),
            ),
        );
    }

    /**
     * Удары снимка.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<int, StrikeSnapshotHit> Удары.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function hits(array $row): array
    {
        $hits = [];
        foreach (SpecShape::list($row, 'hits') as $hit) {
            if (!is_array($hit) || array_is_list($hit)) {
                throw new RuleSpecShapeException('hits');
            }

            $hits[] = new StrikeSnapshotHit(
                SpecShape::string($hit, 'targetKey'),
                SpecShape::int($hit, 'attackSr'),
                SpecShape::optionalString($hit, 'reaction'),
                SpecShape::bool($hit, 'damaged'),
            );
        }

        return $hits;
    }

    /**
     * Область. Нет ключа — пустые компоненты и ноль попаданий.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return AttackScope Область.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function scope(array $row): AttackScope
    {
        $scope = SpecShape::object($row, 'scope') ?? [];
        $hits = $scope['hit_count'] ?? 0;
        if (!is_int($hits) && $hits !== 'all') {
            throw new RuleSpecShapeException('hit_count');
        }

        return new AttackScope(SpecShape::stringList($scope, 'components'), $hits);
    }

    /**
     * Доля шага.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int|float Число.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function fraction(array $row): int|float
    {
        if (!array_key_exists('step_fraction', $row) || $row['step_fraction'] === null) {
            return 0;
        }

        return SpecShape::number($row, 'step_fraction');
    }

    /**
     * Количество состояния.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int|DimensionalNumber|null Значение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function amount(array $row): int|DimensionalNumber|null
    {
        if (!array_key_exists('amount', $row) || $row['amount'] === null) {
            return null;
        }

        $amount = $row['amount'];
        if (is_int($amount)) {
            return $amount;
        }

        if (!is_array($amount) || array_is_list($amount)) {
            throw new RuleSpecShapeException('amount');
        }

        return DimensionalNumbers::pair($amount, 'amount');
    }
}
