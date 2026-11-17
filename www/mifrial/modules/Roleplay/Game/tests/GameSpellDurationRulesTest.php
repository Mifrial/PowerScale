<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellDurationRules;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDuration;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет чтение формы spell.duration.
 */
final class GameSpellDurationRulesTest extends TestCase
{
    /**
     * Instant не создаёт поддержание и не тратит ОД.
     *
     * @return void
     */
    public function testInstant(): void
    {
        $rules = new GameSpellDurationRules();
        $duration = new SpellDuration('instant', null, null, null, null);

        self::assertTrue($rules->isInstant($duration));
        self::assertFalse($rules->isSustained($duration));
        self::assertSame(0, $rules->actionCost($duration));
        $rules->assertSupported($duration);
    }

    /**
     * Sustained поддержан, цена читается из формы.
     *
     * @return void
     */
    public function testSustainedActionCost(): void
    {
        $rules = new GameSpellDurationRules();

        self::assertSame(0, $rules->actionCost(new SpellDuration('sustained', null, null, null, null)));
        self::assertSame(3, $rules->actionCost(new SpellDuration('sustained', null, null, 3, null)));
        self::assertSame(
            2,
            $rules->actionCost(new SpellDuration('sustained', null, null, new DimensionalNumber(1, 1), null)),
        );

        $rules->assertSupported(new SpellDuration('sustained', null, null, null, null));
    }

    /**
     * Lingering поддержан, срок переводится в тики.
     *
     * @return void
     */
    public function testLingeringTicks(): void
    {
        $rules = new GameSpellDurationRules();

        self::assertTrue($rules->isLingering(new SpellDuration('lingering', 1, 'turn', null, null)));
        self::assertSame(1, $rules->lingerTicks(new SpellDuration('lingering', 1, 'turn', null, null)));
        self::assertSame(5, $rules->lingerTicks(new SpellDuration('lingering', 5, 'turn', null, null)));
        self::assertSame(10, $rules->lingerTicks(new SpellDuration('lingering', 1, 'minute', null, null)));
        self::assertSame(30, $rules->lingerTicks(new SpellDuration('lingering', 3, 'minute', null, null)));
        self::assertSame(14400, $rules->lingerTicks(new SpellDuration('lingering', 24, 'hour', null, null)));
        self::assertSame(
            2,
            $rules->lingerTicks(new SpellDuration('lingering', new DimensionalNumber(1, 1), 'turn', null, null)),
        );

        $rules->assertSupported(new SpellDuration('lingering', 1, 'turn', null, null));
    }

    /**
     * Lingering без лимита или с чужой единицей — отказ.
     *
     * @return void
     */
    public function testLingeringRequiresKnownLimit(): void
    {
        $rules = new GameSpellDurationRules();
        foreach ([
            new SpellDuration('lingering', null, null, null, null),
            new SpellDuration('lingering', 0, 'turn', null, null),
            new SpellDuration('lingering', 1, 'day', null, null),
        ] as $duration) {
            try {
                $rules->lingerTicks($duration);
                self::fail('Срок без известного лимита не должен считаться');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * Refreshable и неизвестный вид пока не поддержаны.
     *
     * @return void
     */
    public function testUnsupportedDurations(): void
    {
        $rules = new GameSpellDurationRules();
        foreach (['refreshable', 'unknown'] as $type) {
            try {
                $rules->assertSupported(new SpellDuration($type, null, null, null, null));
                self::fail('Длительность ' . $type . ' не должна быть поддержана');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }
}
