<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\DefenseAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\DodgeRangedPenalty;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AttackHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AutoHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\HitResolution;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\NoneHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\KnownAttackDefense;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverCheck;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverMark;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ManeuverModifier;
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
            SpecShape::optionalString($push, 'pool') ?? '',
            SpecShape::optionalString($push, 'damage') ?? '',
            SpecShape::string($push, 'profiles'),
            self::divisors($push),
            SpecShape::optionalString($push, 'block_profiles') ?? 'any',
            SpecShape::optionalString($push, 'check') ?? 'check-push',
            SpecShape::optionalInt($push, 'damage_modifier') ?? -3,
            SpecShape::optionalInt($push, 'stability_bonus_cap') ?? 6,
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
     * Старая форма `circumstance_delta` отклоняется: источник и величина
     * должны быть раздельными полями.
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

        if (array_key_exists('circumstance_delta', $row)) {
            throw new RuleSpecShapeException('cover_ally.circumstance_delta');
        }

        $advantages = [];
        foreach (SpecShape::list($row, 'defense_advantages') as $entry) {
            $entry = SpecShape::requireMap($entry, 'cover_ally.defense_advantages');
            $source = SpecShape::optionalString($entry, 'source_code');
            if ($source === null || trim($source) === '') {
                throw new RuleSpecShapeException('cover_ally.defense_advantages.source_code');
            }

            $advantages[] = new DefenseAdvantage(SpecShape::int($entry, 'delta'), $source);
        }

        $reactions = SpecShape::stringList($row, 'reactions');
        if ($reactions === [] || in_array('', $reactions, true)) {
            throw new RuleSpecShapeException('cover_ally.reactions');
        }

        $resolution = SpecShape::requireMap($row['resolution'] ?? null, 'cover_ally.resolution');
        $success = SpecShape::optionalString($resolution, 'success');
        $failure = SpecShape::optionalString($resolution, 'failure');
        $becomes = SpecShape::optionalString($resolution, 'becomes');
        if (
            $success !== CoverAlly::SUCCESS_HIGHEST_RATING
            || $failure !== CoverAlly::FAILURE_ATTACKER_CHOICE
            || $becomes !== CoverAlly::BECOMES_ACTUAL_TARGET
        ) {
            throw new RuleSpecShapeException('cover_ally.resolution');
        }

        return new CoverAlly(
            $advantages,
            $reactions,
            [
                'success' => (string) $success,
                'failure' => (string) $failure,
                'becomes' => (string) $becomes,
            ],
            SpecShape::optionalString($row, 'distance'),
        );
    }

    /**
     * Обманный манёвр.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ManeuverModifier|null Манёвр или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function maneuver(array $document): ?ManeuverModifier
    {
        $row = SpecShape::object($document, 'maneuver');
        if ($row === null) {
            return null;
        }

        if (SpecShape::string($row, 'trigger') !== ManeuverModifier::TRIGGER_STRIKE) {
            throw new RuleSpecShapeException('maneuver.trigger');
        }

        if (SpecShape::string($row, 'window') !== ManeuverModifier::WINDOW_AFTER_DEFENSE_BEFORE_CHECK) {
            throw new RuleSpecShapeException('maneuver.window');
        }

        if (SpecShape::string($row, 'attack') !== ManeuverModifier::ATTACK_AUTO_FAIL) {
            throw new RuleSpecShapeException('maneuver.attack');
        }

        $checks = SpecShape::object($row, 'check');
        if ($checks === null) {
            throw new RuleSpecShapeException('maneuver.check');
        }

        $attacker = self::maneuverChecks($checks, 'attacker');
        $target = self::maneuverChecks($checks, 'target');
        if ($attacker === [] || $target === []) {
            throw new RuleSpecShapeException('maneuver.check');
        }

        $outcome = SpecShape::object($row, 'outcome');
        if ($outcome === null) {
            throw new RuleSpecShapeException('maneuver.outcome');
        }

        if (SpecShape::string($outcome, 'on_target_passed') !== ManeuverModifier::OUTCOME_MAY_IGNORE) {
            throw new RuleSpecShapeException('maneuver.outcome.on_target_passed');
        }

        $marks = [];
        foreach (SpecShape::list($outcome, 'on_target_failed') as $entry) {
            $entry = SpecShape::requireMap($entry, 'maneuver.outcome.on_target_failed');
            if (SpecShape::string($entry, 'kind') !== ManeuverModifier::MARK_KIND_ADVANTAGE) {
                throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.kind');
            }

            $value = SpecShape::int($entry, 'value');
            if ($value === 0) {
                throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.value');
            }

            $source = SpecShape::string($entry, 'source_code');
            if ($source === '') {
                throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.source_code');
            }

            if (SpecShape::string($entry, 'scope') !== ManeuverModifier::SCOPE_DEFENSE_AGAINST_ATTACKER) {
                throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.scope');
            }

            if (SpecShape::string($entry, 'consumes') !== ManeuverModifier::CONSUMES_FIRST_DEFENSE) {
                throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.consumes');
            }

            if (SpecShape::string($entry, 'expires') !== ManeuverModifier::EXPIRES_ATTACKER_TURN) {
                throw new RuleSpecShapeException('maneuver.outcome.on_target_failed.expires');
            }

            $marks[] = new ManeuverMark(
                ManeuverModifier::MARK_KIND_ADVANTAGE,
                $value,
                $source,
                ManeuverModifier::SCOPE_DEFENSE_AGAINST_ATTACKER,
                ManeuverModifier::CONSUMES_FIRST_DEFENSE,
                ManeuverModifier::EXPIRES_ATTACKER_TURN,
            );
        }

        if ($marks === []) {
            throw new RuleSpecShapeException('maneuver.outcome.on_target_failed');
        }

        return new ManeuverModifier(
            ManeuverModifier::TRIGGER_STRIKE,
            ManeuverModifier::WINDOW_AFTER_DEFENSE_BEFORE_CHECK,
            ManeuverModifier::ATTACK_AUTO_FAIL,
            $attacker,
            $target,
            true,
            $marks,
        );
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
     * Штраф к эффективности уклонения от дальней атаки.
     *
     * Сейчас правило описывает только выстрел: `profile_type = shoot`.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return DodgeRangedPenalty|null Штраф или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function dodgePenalty(array $document): ?DodgeRangedPenalty
    {
        $row = SpecShape::object($document, 'dodge_ranged_penalty');
        if ($row === null) {
            return null;
        }

        return new DodgeRangedPenalty(
            SpecShape::string($row, 'profile_type'),
            DimensionalNumbers::required($row, 'distance'),
            SpecShape::int($row, 'delta'),
        );
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
            $mode = SpecShape::requireMap($mode, 'modes');

            $modes[] = new StrikeUpgradeMode(
                SpecShape::string($mode, 'code'),
                SpecShape::string($mode, 'label'),
                SpecShape::int($mode, 'injury_check_advantage'),
            );
        }

        return new StrikeUpgrade(SpecShape::string($row, 'exclusive_group'), $modes, SpecShape::bool($row, 'requires_physiology'));
    }

    /**
     * Пары проверок одной стороны манёвра.
     *
     * @param array<string, mixed> $checks Узел check.
     * @param string $side Сторона.
     *
     * @return array<int, ManeuverCheck> Пары.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function maneuverChecks(array $checks, string $side): array
    {
        $rows = [];
        foreach (SpecShape::list($checks, $side) as $entry) {
            $entry = SpecShape::requireMap($entry, 'maneuver.check.' . $side);
            $check = SpecShape::string($entry, 'check');
            $characteristic = SpecShape::string($entry, 'characteristic');
            if ($check === '' || $characteristic === '') {
                throw new RuleSpecShapeException('maneuver.check.' . $side);
            }

            $rows[] = new ManeuverCheck($check, $characteristic);
        }

        return $rows;
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
            if (!is_int($value)) {
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
            $step = SpecShape::requireMap($step, 'steps');

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
