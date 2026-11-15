<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\MagicPathSpecs;
use PHPUnit\Framework\TestCase;

/**
 * План M10: признаки пути — без Контроля и каст из записей источника.
 */
final class MagicPathSpecTest extends TestCase
{
    /**
     * Флаги читаются из спеки.
     *
     * @return void
     */
    public function testRecordedCastingWithoutControl(): void
    {
        $path = MagicPathSpecs::read([
            'check_code' => 'cast-check',
            'cast_check_code' => 'cast-check',
            'power_characteristic_code' => 'magic-power',
            'control_characteristic_code' => null,
            'includes_path_codes' => [],
            'no_control' => true,
            'recorded_casting' => true,
        ]);

        self::assertTrue($path->isControlless());
        self::assertTrue($path->castsRecorded());
    }

    /**
     * Обычный путь без флагов.
     *
     * @return void
     */
    public function testPlainPathKeepsControlAndStudy(): void
    {
        $path = MagicPathSpecs::read([
            'check_code' => 'cast-check',
            'cast_check_code' => 'cast-check',
            'power_characteristic_code' => 'magic-power',
            'control_characteristic_code' => 'magic-control',
            'includes_path_codes' => [],
        ]);

        self::assertFalse($path->isControlless());
        self::assertFalse($path->castsRecorded());
        self::assertNull($path->getActiveSpellLimit());
    }

    /**
     * Предел активных волшебств читается и отвергает неположительное значение.
     *
     * @return void
     */
    public function testActiveSpellLimit(): void
    {
        $path = MagicPathSpecs::read([
            'check_code' => 'cast-check',
            'cast_check_code' => 'cast-check',
            'power_characteristic_code' => 'magic-power',
            'control_characteristic_code' => 'magic-control',
            'includes_path_codes' => [],
            'active_spell_limit' => 1,
        ]);

        self::assertSame(1, $path->getActiveSpellLimit());

        $this->expectException(RuleSpecShapeException::class);
        MagicPathSpecs::read([
            'check_code' => 'cast-check',
            'cast_check_code' => 'cast-check',
            'includes_path_codes' => [],
            'active_spell_limit' => 0,
        ]);
    }
}
