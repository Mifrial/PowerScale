<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellChainHop;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChain;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamage;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет арифметику прыжка цепи.
 */
final class GameSpellChainHopTest extends TestCase
{
    /**
     * Цель сразу после попадания другой не считается.
     *
     * @return void
     */
    public function testRejectsSameTargetAsLastHit(): void
    {
        $hop = new GameSpellChainHop();

        self::assertFalse($hop->canHit($this->chain(), 'npc:1', 'npc:1'));
        self::assertTrue($hop->canHit($this->chain(), 'npc:2', 'npc:1'));
    }

    /**
     * Прыжок теряет размер урона.
     *
     * @return void
     */
    public function testNextAmountReducesSize(): void
    {
        $next = (new GameSpellChainHop())->nextAmount(
            $this->chain(),
            null,
            new CharacteristicNumber(4, 0),
            0,
        );

        self::assertInstanceOf(CharacteristicNumber::class, $next);
        self::assertSame(4, $next->getBase());
        self::assertSame(-1, $next->getSize());
    }

    /**
     * Ниже минимума цепи прыжок не наносится.
     *
     * @return void
     */
    public function testNextAmountStopsBelowMin(): void
    {
        $next = (new GameSpellChainHop())->nextAmount(
            $this->chain(),
            null,
            new CharacteristicNumber(3, -1),
            0,
        );

        self::assertNull($next);
    }

    /**
     * Снижение по дистанции идёт до потери размера за прыжок.
     *
     * @param int $distanceIpari Дистанция.
     * @param int $base Ожидаемая база.
     * @param int $size Ожидаемый размер.
     *
     * @return void
     */
    #[DataProvider('falloffProvider')]
    public function testNextAmountAppliesFalloffFirst(int $distanceIpari, int $base, int $size): void
    {
        $next = (new GameSpellChainHop())->nextAmount(
            $this->chain(min: new DimensionalNumber(3, -2)),
            $this->damage(),
            new CharacteristicNumber(4, 0),
            $distanceIpari,
        );

        self::assertInstanceOf(CharacteristicNumber::class, $next);
        self::assertSame($base, $next->getBase());
        self::assertSame($size, $next->getSize());
    }

    /**
     * Дистанции прыжка.
     *
     * @return iterable<string, array{int, int, int}> Наборы.
     */
    public static function falloffProvider(): iterable
    {
        yield 'free' => [1, 4, -1];
        yield 'one extra' => [2, 4, -2];
    }

    /**
     * Снижение по дистанции может оборвать цепь раньше минимума цепи.
     *
     * @return void
     */
    public function testFalloffStopsAboveChainMin(): void
    {
        $next = (new GameSpellChainHop())->nextAmount(
            $this->chain(min: new DimensionalNumber(3, -1)),
            $this->damage(),
            new CharacteristicNumber(4, 0),
            3,
        );

        self::assertNull($next);
    }

    /**
     * Неизвестное значение формы — отказ.
     *
     * @param array<string, mixed> $spec Поля цепи.
     *
     * @return void
     */
    #[DataProvider('unsupportedProvider')]
    public function testRejectsUnknownForm(array $spec): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameSpellChainHop())->canHit($this->chain($spec), 'npc:2', 'npc:1');
    }

    /**
     * Неизвестные значения.
     *
     * @return iterable<string, array{array<string, mixed>}> Наборы.
     */
    public static function unsupportedProvider(): iterable
    {
        yield 'retarget' => [['retarget' => 'nearest']];
        yield 'same target' => [['same_target' => 'forbidden']];
    }

    /**
     * Цепь.
     *
     * @param array<string, mixed> $extra Поля.
     * @param DimensionalNumber|null $min Минимум цепи.
     *
     * @return SpellChain Цепь.
     */
    private function chain(array $extra = [], ?DimensionalNumber $min = null): SpellChain
    {
        return new SpellChain(
            1,
            $min ?? new DimensionalNumber(3, -1),
            (string) ($extra['retarget'] ?? 'from_last_hit'),
            (string) ($extra['same_target'] ?? 'via_other'),
        );
    }

    /**
     * Урон с дистанцией.
     *
     * @return SpellDamage Урон.
     */
    private function damage(): SpellDamage
    {
        return new SpellDamage('electricity', 'electromancy', [], 1, 1, new DimensionalNumber(3, -2));
    }
}
