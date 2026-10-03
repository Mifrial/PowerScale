<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AttackHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AutoHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\HitResolution;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\NoneHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\KnownAttackDefense;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\NextCastDifficulty;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChain;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChargeCap;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChargeCapStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellSaturation;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellTouch;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellUpgrade;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeUpgrade;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeUpgradeMode;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Боевые поля способности. Это контракт правила, не расчёт боя.
 */
final class AbilityCombat
{
    /**
     * Доставка попадания. Нет ключа — null.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return HitResolution|null Доставка или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function hit(array $document): ?HitResolution
    {
        $hit = SpecShape::object($document, 'hit_resolution');
        if ($hit === null) {
            return null;
        }

        $type = SpecShape::string($hit, 'type');

        return match ($type) {
            'none' => new NoneHit(),
            'attack' => new AttackHit(),
            'auto' => new AutoHit(SpecShape::int($hit, 'rating')),
            default => throw new RuleSpecShapeException('hit_resolution'),
        };
    }

    /**
     * Толчок.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return PushSpec|null Толчок или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function push(array $document): ?PushSpec
    {
        $push = SpecShape::object($document, 'push');
        if ($push === null) {
            return null;
        }

        return new PushSpec(
            SpecShape::string($push, 'pool'),
            SpecShape::string($push, 'damage'),
            SpecShape::string($push, 'profiles'),
            self::divisors($push),
        );
    }

    /**
     * Насыщение.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return SpellSaturation|null Насыщение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function saturation(array $document): ?SpellSaturation
    {
        $row = SpecShape::object($document, 'spell_saturation');
        if ($row === null) {
            return null;
        }

        return new SpellSaturation(
            SpecShape::int($row, 'min_rating'),
            SpecShape::int($row, 'rating_per_step'),
            SpecShape::int($row, 'power_per_step'),
        );
    }

    /**
     * Следующая сложность.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return NextCastDifficulty|null Сложность или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function nextDifficulty(array $document): ?NextCastDifficulty
    {
        $row = SpecShape::object($document, 'next_cast_difficulty');
        if ($row === null) {
            return null;
        }

        return new NextCastDifficulty(
            SpecShape::int($row, 'min_remaining_rating'),
            SpecShape::int($row, 'delta'),
            SpecShape::optionalString($row, 'source_code'),
        );
    }

    /**
     * Касание.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return SpellTouch|null Касание или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function touch(array $document): ?SpellTouch
    {
        $row = SpecShape::object($document, 'spell_touch');
        if ($row === null) {
            return null;
        }

        return new SpellTouch(SpecShape::bool($row, 'weapon_damage'), SpecShape::int($row, 'attack_sr_bonus'));
    }

    /**
     * Прикрытие.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return CoverAlly|null Прикрытие или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function cover(array $document): ?CoverAlly
    {
        $row = SpecShape::object($document, 'cover_ally');
        if ($row === null) {
            return null;
        }

        return new CoverAlly(SpecShape::int($row, 'circumstance_delta'));
    }

    /**
     * Защита от известной атаки.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return KnownAttackDefense|null Защита или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function knownDefense(array $document): ?KnownAttackDefense
    {
        $row = SpecShape::object($document, 'known_attack_defense');
        if ($row === null) {
            return null;
        }

        return new KnownAttackDefense(SpecShape::int($row, 'delta'), SpecShape::optionalString($row, 'source_code'));
    }

    /**
     * Улучшение заклинания.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return SpellUpgrade|null Улучшение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function spellUpgrade(array $document): ?SpellUpgrade
    {
        $row = SpecShape::object($document, 'spell_upgrade');
        if ($row === null) {
            return null;
        }

        return new SpellUpgrade(
            SpecShape::int($row, 'action_point_delta'),
            self::chargeCap($row),
            SpecShape::optionalInt($row, 'check_advantage'),
            SpecShape::bool($row, 'any_path'),
            SpecShape::optionalInt($row, 'resistance_penetration_per_step'),
            self::chain($row),
        );
    }

    /**
     * Улучшение удара.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return StrikeUpgrade|null Улучшение или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function strikeUpgrade(array $document): ?StrikeUpgrade
    {
        $row = SpecShape::object($document, 'strike_upgrade');
        if ($row === null) {
            return null;
        }

        $modes = [];
        foreach (SpecShape::list($row, 'modes') as $mode) {
            if (!is_array($mode) || array_is_list($mode)) {
                throw new RuleSpecShapeException('modes');
            }

            $modes[] = new StrikeUpgradeMode(
                SpecShape::string($mode, 'code'),
                SpecShape::string($mode, 'label'),
                SpecShape::int($mode, 'injury_check_advantage'),
            );
        }

        return new StrikeUpgrade(SpecShape::string($row, 'exclusive_group'), $modes, SpecShape::bool($row, 'requires_physiology'));
    }

    /**
     * Делители толчка.
     *
     * @param array<string, mixed> $push Толчок.
     *
     * @return array<string, int> Словарь.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function divisors(array $push): array
    {
        $source = SpecShape::object($push, 'posture_rating_divisor_by_damage_type') ?? [];
        $divisors = [];
        foreach ($source as $code => $value) {
            if (!is_string($code) || !is_int($value)) {
                throw new RuleSpecShapeException('posture_rating_divisor_by_damage_type');
            }

            $divisors[$code] = $value;
        }

        return $divisors;
    }

    /**
     * Потолок зарядов.
     *
     * @param array<string, mixed> $upgrade Улучшение.
     *
     * @return SpellChargeCap|null Потолок или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function chargeCap(array $upgrade): ?SpellChargeCap
    {
        $cap = SpecShape::object($upgrade, 'charge_cap');
        if ($cap === null) {
            return null;
        }

        $steps = [];
        foreach (SpecShape::list($cap, 'steps') as $step) {
            if (!is_array($step) || array_is_list($step)) {
                throw new RuleSpecShapeException('steps');
            }

            $steps[] = new SpellChargeCapStep(
                SpecShape::int($step, 'min_experience'),
                SpecShape::optionalInt($step, 'cap'),
                SpecShape::optionalInt($step, 'per_experience'),
            );
        }

        return new SpellChargeCap(SpecShape::int($cap, 'base'), SpecShape::string($cap, 'experience_keyword_code'), $steps);
    }

    /**
     * Цепь.
     *
     * @param array<string, mixed> $upgrade Улучшение.
     *
     * @return SpellChain|null Цепь или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function chain(array $upgrade): ?SpellChain
    {
        $chain = SpecShape::object($upgrade, 'chain');
        if ($chain === null) {
            return null;
        }

        return new SpellChain(
            SpecShape::int($chain, 'damage_size_per_hop'),
            DimensionalNumbers::required($chain, 'min'),
            SpecShape::string($chain, 'retarget'),
            SpecShape::string($chain, 'same_target'),
        );
    }
}
