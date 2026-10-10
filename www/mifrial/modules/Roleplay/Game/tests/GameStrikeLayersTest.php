<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Game\Service\GameStrikeAmounts;
use Mifrial\Roleplay\Game\Service\GameStrikeLayers;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет порядок filter, reliability cut и source collapse без MySQL.
 */
final class GameStrikeLayersTest extends TestCase
{
    /**
     * Null и порог выше рейтинга сохраняют слой, порог не выше снимает его.
     *
     * @param int|null $durability Порог слоя.
     * @param bool $cut Включён ли срез.
     * @param int $expectedBase Ожидаемая сумма.
     *
     * @return void
     *
     * @dataProvider durabilityProvider
     */
    public function testDurabilityThresholdIsAppliedBeforeSourceCollapse(
        ?int $durability,
        bool $cut,
        int $expectedBase,
    ): void {
        $layers = [
            new CharacterCombatLayer(
                'resistance',
                new DimensionalNumber(5, 0),
                $durability,
                'armor',
                'piercing',
            ),
            new CharacterCombatLayer(
                'resistance',
                new DimensionalNumber(1, 0),
                null,
                'armor',
                'piercing',
            ),
            new CharacterCombatLayer(
                'defense',
                new DimensionalNumber(2, 0),
                null,
                'shield',
                'piercing',
            ),
            new CharacterCombatLayer(
                'resistance',
                new DimensionalNumber(9, 0),
                0,
                'other',
                'fire',
            ),
        ];

        $amount = $this->layers($layers, $cut)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
        );

        self::assertSame($expectedBase, $amount->getBase());
    }

    /**
     * Defense и resistance используют одинаковую threshold semantics.
     *
     * @return void
     */
    public function testDefenseAndResistanceUseSameThresholdSemantics(): void
    {
        $layers = [
            new CharacterCombatLayer('defense', new DimensionalNumber(4, 0), 1, '', 'piercing'),
            new CharacterCombatLayer('resistance', new DimensionalNumber(3, 0), 1, '', 'piercing'),
        ];

        $amount = $this->layers($layers, true)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
        );

        self::assertSame(0, $amount->getBase());
    }

    /**
     * Penetration вычитает только defense и сохраняет resistance того же source.
     *
     * @return void
     */
    public function testPenetrationChangesDefenseOnlyAcrossSharedSource(): void
    {
        $layers = [
            new CharacterCombatLayer('defense', new DimensionalNumber(8, 0), null, 'shared', 'piercing'),
            new CharacterCombatLayer('resistance', new DimensionalNumber(3, 0), null, 'shared', 'piercing'),
        ];

        $amount = $this->layers($layers, false)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
            new DimensionalNumber(4, 0),
        );

        self::assertSame(7, $amount->getBase());
        self::assertSame(0, $amount->getSize());
    }

    /**
     * Penetration не изменяет resistance при другом source.
     *
     * @return void
     */
    public function testPenetrationLeavesResistanceInvariantAcrossDifferentSources(): void
    {
        $layers = [
            new CharacterCombatLayer('defense', new DimensionalNumber(8, 0), null, 'shield', 'piercing'),
            new CharacterCombatLayer('resistance', new DimensionalNumber(3, 0), null, 'armor', 'piercing'),
        ];

        $amount = $this->layers($layers, false)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
            new DimensionalNumber(4, 0),
        );

        self::assertSame(7, $amount->getBase());
        self::assertSame(0, $amount->getSize());
    }

    /**
     * Размеры defense и penetration выравниваются native arithmetic.
     *
     * @return void
     */
    public function testPenetrationAlignsDimensionalSizes(): void
    {
        $layers = [
            new CharacterCombatLayer('defense', new DimensionalNumber(2, 1), null, '', 'piercing'),
        ];

        $amount = $this->layers($layers, false)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
            new DimensionalNumber(1, 0),
        );

        self::assertSame(3, $amount->getBase());
        self::assertSame(0, $amount->getSize());
    }

    /**
     * Нулевое и отрицательное penetration сохраняют native floor semantics.
     *
     * @return void
     */
    public function testPenetrationZeroAndNegativeValuesAreDimensional(): void
    {
        $layers = [
            new CharacterCombatLayer('defense', new DimensionalNumber(2, 0), null, '', 'piercing'),
        ];

        $zero = $this->layers($layers, false)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
            new DimensionalNumber(0, 0),
        );
        $negative = $this->layers($layers, false)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
            new DimensionalNumber(-1, 0),
        );

        self::assertSame(2, $zero->getBase());
        self::assertSame(3, $negative->getBase());
    }

    /**
     * Penetration больше defense даёт размерный ноль.
     *
     * @return void
     */
    public function testPenetrationFloorsDefenseAtZero(): void
    {
        $layers = [
            new CharacterCombatLayer('defense', new DimensionalNumber(2, 0), null, '', 'piercing'),
        ];

        $amount = $this->layers($layers, false)->total(
            $this->damageTypeSlice(),
            'piercing',
            [],
            [],
            'dodge',
            null,
            1,
            new DimensionalNumber(3, 0),
        );

        self::assertSame(0, $amount->getBase());
        self::assertSame(0, $amount->getSize());
    }

    /**
     * Пороговые варианты durability.
     *
     * @return array<string, array{int|null, bool, int}> Cases.
     */
    public static function durabilityProvider(): array
    {
        return [
            'null remains' => [null, true, 7],
            'above rating remains' => [2, true, 7],
            'equal rating is removed before collapse' => [1, true, 3],
            'below rating is removed before collapse' => [0, true, 3],
            'cut disabled keeps layer' => [1, false, 7],
        ];
    }

    /**
     * Собирает pure GameStrikeLayers с заданными projected layers.
     *
     * @param array<int, CharacterCombatLayer> $projectedLayers Слои.
     * @param bool $cut Capability.
     *
     * @return GameStrikeLayers Расчёт.
     */
    private function layers(array $projectedLayers, bool $cut): GameStrikeLayers
    {
        $projection = $this->createMock(ICharacterCombatLayers::class);
        $projection->method('project')->willReturn($projectedLayers);
        $engine = $this->createMock(IMechanicEngine::class);
        $engine->expects(self::once())->method('hasReliabilityCut')->willReturn($cut);
        $mechanics = $this->createMock(IMechanics::class);
        $mechanics->method('getList')->willReturn([]);

        return new GameStrikeLayers(
            $projection,
            $engine,
            $mechanics,
            new GameStrikeAmounts(),
        );
    }

    /**
     * Срез с живым damage type.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function damageTypeSlice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(
            1,
            1,
            'world',
            [new CharacterResolvedRule(
                new RuleVersionRecord(
                    1,
                    1,
                    'piercing',
                    true,
                    'damage_type',
                    'Piercing',
                    '',
                    [
                        'forms' => ['genitive' => 'piercing', 'dative' => 'piercing'],
                        'defense_ignored' => false,
                        'modifies_spell_difficulty' => false,
                    ],
                    [],
                    [['mechanic_id' => 1, 'mechanic_payload' => []]],
                    'needs_work',
                    '',
                    DateTime::now(),
                ),
                [],
            )],
            [],
        );
    }
}
