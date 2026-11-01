<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GamePushRules;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GamePushRulesTest extends TestCase
{
    /**
     * Код проверки и сдвиг урона берутся из спеки.
     *
     * @return void
     */
    public function testCheckAndDamageModifierComeFromSpec(): void
    {
        $rules = (new ReflectionClass(GamePushRules::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(GamePushRules::class, $rules);
        $push = new PushSpec('', '', 'hands_or_shield', [], 'hands_or_shield', 'check-push', -3, 6);

        self::assertSame('check-push', $rules->checkCode($push));
        self::assertSame(-3, $rules->damageModifier($push));
    }

    /**
     * Устойчивость: игнор — минимум, блок — Сила плюс-минус РУ, не ниже минимума.
     *
     * @return void
     */
    public function testStabilityUsesFloorAndBlockRating(): void
    {
        $rules = (new ReflectionClass(GamePushRules::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(GamePushRules::class, $rules);
        $push = new PushSpec('', '', 'hands_or_shield', [], 'hands_or_shield', 'check-push', -3, 6);
        $floor = new CharacteristicNumber(3, -1);
        $strength = new CharacteristicNumber(5, 0);

        self::assertSame(
            ['base' => 3, 'size' => -1],
            $this->view($rules->stability($push, 'ignore', 5, $strength, $floor)),
        );
        self::assertSame(
            ['base' => 4, 'size' => 0],
            $this->view($rules->stability($push, 'block', 1, $strength, $floor)),
        );
        self::assertSame(
            ['base' => 5, 'size' => 1],
            $this->view($rules->stability($push, 'block', -3, $strength, $floor)),
        );
    }

    /**
     * Неустойчивость — разница Сил, урезанная делителем типа урона.
     *
     * @return void
     */
    public function testUnstableSubtractsStabilityAndDivides(): void
    {
        $rules = (new ReflectionClass(GamePushRules::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(GamePushRules::class, $rules);
        $push = new PushSpec(
            '',
            '',
            'slashing_or_blunt_strike',
            ['slashing' => 2],
            'slashing_or_blunt_strike',
            'check-push',
            -1,
            6,
        );
        $attacker = new CharacteristicNumber(5, 0);

        self::assertSame(1, $rules->unstable($push, $attacker, new CharacteristicNumber(4, 0), null));
        self::assertSame(5, $rules->unstable($push, $attacker, new CharacteristicNumber(3, -1), null));
        self::assertSame(2, $rules->unstable($push, $attacker, new CharacteristicNumber(3, -1), 'slashing'));
    }

    /**
     * Отброс — четверть рейтинга позы; меньше четырёх — отброса нет.
     *
     * @return void
     */
    public function testKnockbackDividesPostureByFour(): void
    {
        $rules = (new ReflectionClass(GamePushRules::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(GamePushRules::class, $rules);

        self::assertNull($rules->knockback(3));
        self::assertSame(['base' => 1, 'size' => 0], $this->view($rules->knockback(4)));
        self::assertSame(['base' => 3, 'size' => 0], $this->view($rules->knockback(13)));
    }

    /**
     * Пара отброса.
     *
     * @param DimensionalNumber|null $value Значение.
     *
     * @return array{base: int, size: int}|null Пара.
     */
    private function view(?DimensionalNumber $value): ?array
    {
        return $value === null ? null : ['base' => $value->getBase(), 'size' => $value->getSize()];
    }
}
