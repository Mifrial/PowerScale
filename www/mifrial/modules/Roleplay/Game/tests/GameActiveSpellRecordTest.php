<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет чтение нормализованной строки активного заклинания.
 */
final class GameActiveSpellRecordTest extends TestCase
{
    /**
     * Строка без активационных параметров и улучшений читается.
     *
     * @return void
     */
    public function testEmptyParametersAndUpgrades(): void
    {
        $row = $this->row([
            'parameter_values' => [],
            'applied_upgrade_codes' => [],
        ]);

        $record = GameActiveSpellRecord::fromNormalized($row);

        self::assertSame([], $record->getParameterValues());
        self::assertSame([], $record->getAppliedUpgradeCodes());
        self::assertSame(['base' => 4, 'size' => 0], $record->getSustainPower());
    }

    /**
     * Карта параметров и список улучшений читаются.
     *
     * @return void
     */
    public function testParametersAndUpgrades(): void
    {
        $record = GameActiveSpellRecord::fromNormalized($this->row([
            'parameter_values' => [
                'x' => ['base' => 1, 'size' => 0],
                'level' => 2,
            ],
            'applied_upgrade_codes' => ['careful'],
        ]));

        self::assertSame(
            ['x' => ['base' => 1, 'size' => 0], 'level' => 2],
            $record->getParameterValues(),
        );
        self::assertSame(['careful'], $record->getAppliedUpgradeCodes());
    }

    /**
     * Непустой список вместо карты параметров отклоняется.
     *
     * @return void
     */
    public function testRejectsListParameters(): void
    {
        $this->expectException(GameInvalidException::class);
        GameActiveSpellRecord::fromNormalized($this->row([
            'parameter_values' => [['base' => 1, 'size' => 0]],
        ]));
    }

    /**
     * Битая размерная пара отклоняется.
     *
     * @return void
     */
    public function testRejectsBrokenPair(): void
    {
        $this->expectException(GameInvalidException::class);
        GameActiveSpellRecord::fromNormalized($this->row([
            'used_power' => ['base' => 1],
        ]));
    }

    /**
     * Полная строка с подменой полей.
     *
     * @param array<string, mixed> $overrides Поля.
     *
     * @return array<string, mixed> Строка.
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'game_id' => 1,
            'session_id' => 2,
            'battle_id' => 3,
            'caster_kind' => 'character',
            'caster_id' => 4,
            'spell_code' => 'lightning-generator',
            'path_code' => 'arcanist',
            'source_key' => 'characteristic:magic-power',
            'duration_type' => 'sustained',
            'used_power' => ['base' => 4, 'size' => 0],
            'sustain_power' => ['base' => 4, 'size' => 0],
            'parameter_values' => [],
            'applied_upgrade_codes' => [],
            'started_round' => 1,
            'started_participant' => 'character:4',
            'caster_sheet_version' => 10,
            'state_version' => 1,
        ], $overrides);
    }
}
