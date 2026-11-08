<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\StrikeManeuverSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Узлы strike_maneuver и maneuver_boost.
 */
final class StrikeManeuverSpecsTest extends TestCase
{
    /**
     * Минимальный манёвр читается с мастерством и эффектом.
     *
     * @return void
     */
    public function testManeuverReadsThresholdMasteryAndEffect(): void
    {
        $maneuver = StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 3,
                'min_weapon_mastery' => 3,
                'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
            ],
        ]);

        self::assertNotNull($maneuver);
        self::assertSame(3, $maneuver->getMinSuccess());
        self::assertSame(3, $maneuver->getMinWeaponMastery());
        self::assertCount(1, $maneuver->getEffects());
        self::assertSame('state', $maneuver->getEffects()[0]->getKind());
        self::assertSame('unstable', $maneuver->getEffects()[0]->getState());
        self::assertSame(1, $maneuver->getEffects()[0]->getAmount());
    }

    /**
     * Нет узла — нет манёвра.
     *
     * @return void
     */
    public function testManeuverAbsent(): void
    {
        self::assertNull(StrikeManeuverSpecs::read(['type' => 'action']));
        self::assertNull(StrikeManeuverSpecs::boost(['type' => 'skill']));
    }

    /**
     * Мастерство можно не задавать.
     *
     * @return void
     */
    public function testManeuverWithoutMastery(): void
    {
        $maneuver = StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 3,
                'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
            ],
        ]);

        self::assertNotNull($maneuver);
        self::assertNull($maneuver->getMinWeaponMastery());
    }

    /**
     * Старая форма эффекта больше не читается.
     *
     * @return void
     */
    public function testManeuverRejectsLegacyEffectShape(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 3,
                'effects' => [['type' => 'unstable', 'amount' => 1]],
            ],
        ]);
    }

    /**
     * Порог меньше единицы — битая спека.
     *
     * @return void
     */
    public function testManeuverRejectsZeroThreshold(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 0,
                'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
            ],
        ]);
    }

    /**
     * Пустой список эффектов — битая спека.
     *
     * @return void
     */
    public function testManeuverRejectsEmptyEffects(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 3,
                'effects' => [],
            ],
        ]);
    }

    /**
     * Неизвестный вид эффекта — битая спека.
     *
     * @return void
     */
    public function testManeuverRejectsUnknownEffectKind(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 3,
                'effects' => [['kind' => 'teleport', 'state' => 'unstable', 'amount' => 1]],
            ],
        ]);
    }

    /**
     * Эффект без состояния — битая спека.
     *
     * @return void
     */
    public function testManeuverRejectsEmptyState(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        StrikeManeuverSpecs::read([
            'type' => 'action',
            'strike_maneuver' => [
                'min_success' => 3,
                'effects' => [['kind' => 'state', 'state' => '', 'amount' => 1]],
            ],
        ]);
    }

    /**
     * Усиление читает манёвр, мастерство, замену и эффекты.
     *
     * @return void
     */
    public function testBoostReadsContract(): void
    {
        $boost = StrikeManeuverSpecs::boost([
            'type' => 'skill',
            'maneuver_boost' => [
                'maneuvers' => ['zastavit-otkrytsya', 'razbit-zaschitu'],
                'min_success' => 4,
                'min_weapon_mastery' => 5,
                'replaces' => [['kind' => 'state', 'state' => 'unstable']],
                'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 2]],
            ],
        ]);

        self::assertNotNull($boost);
        self::assertSame(['zastavit-otkrytsya', 'razbit-zaschitu'], $boost->getManeuvers());
        self::assertSame(4, $boost->getMinSuccess());
        self::assertSame(5, $boost->getMinWeaponMastery());
        self::assertCount(1, $boost->getReplaces());
        self::assertSame('unstable', $boost->getReplaces()[0]->getState());
        self::assertSame(2, $boost->getEffects()[0]->getAmount());
    }

    /**
     * Пустой манёвр — битая спека.
     *
     * @return void
     */
    public function testBoostRejectsEmptyManeuver(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        StrikeManeuverSpecs::boost([
            'type' => 'skill',
            'maneuver_boost' => [
                'maneuvers' => [],
                'min_success' => 4,
                'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 2]],
            ],
        ]);
    }
}
