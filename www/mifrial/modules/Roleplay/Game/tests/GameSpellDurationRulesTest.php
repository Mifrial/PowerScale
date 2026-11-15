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
     * Lingering и refreshable пока не поддержаны.
     *
     * @return void
     */
    public function testUnsupportedDurations(): void
    {
        $rules = new GameSpellDurationRules();
        foreach (['lingering', 'refreshable', 'unknown'] as $type) {
            try {
                $rules->assertSupported(new SpellDuration($type, null, null, null, null));
                self::fail('Длительность ' . $type . ' не должна быть поддержана');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }
}
