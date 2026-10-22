<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Service\GameCheckRoll;
use Mifrial\Roleplay\Mechanic\Dto\CheckRating;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Mechanic\Dto\RollMechanicPayload;
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет pure-границу размерного auto-fail попадания.
 */
final class GameCheckRollTest extends TestCase
{
    /**
     * Размерный ноль не является auto-fail, а минимум и значения ниже него являются.
     *
     * @return void
     */
    public function testCombatAutoFailUsesDimensionalMinimum(): void
    {
        $roll = new GameCheckRoll(
            $this->createStub(ICharacterRuleSlices::class),
            $this->createStub(ICharacters::class),
            $this->createStub(IMechanics::class),
            $this->createStub(IMechanicRolls::class),
        );

        self::assertFalse($roll->isCombatAutoFail(['base' => 0, 'size' => 0]));
        self::assertFalse($roll->isCombatAutoFail(['base' => 0, 'size' => 3]));
        self::assertTrue($roll->isCombatAutoFail(['base' => 0, 'size' => -1]));
        self::assertTrue($roll->isCombatAutoFail(['base' => 0, 'size' => -3]));
        self::assertTrue($roll->isCombatAutoFail(['base' => -1, 'size' => 0]));
    }

    /**
     * Проверяет auto-fail по размерному roll внутри полного результата атакующего.
     *
     * @return void
     */
    public function testAttackerResultUsesNestedRollForAutoFail(): void
    {
        $roll = new GameCheckRoll(
            $this->createStub(ICharacterRuleSlices::class),
            $this->createStub(ICharacters::class),
            $this->createStub(IMechanics::class),
            $this->createStub(IMechanicRolls::class),
        );

        $results = [
            ['difficulty' => 2, 'roll' => ['base' => 1, 'size' => 0], 'success' => 1, 'rating' => 1],
            ['difficulty' => 2, 'roll' => ['base' => 0, 'size' => 0], 'success' => 1, 'rating' => 1],
            ['difficulty' => 2, 'roll' => ['base' => 0, 'size' => -1], 'success' => 0, 'rating' => 0],
        ];

        self::assertFalse($roll->isCombatAutoFail($results[0]['roll']));
        self::assertFalse($roll->isCombatAutoFail($results[1]['roll']));
        self::assertTrue($roll->isCombatAutoFail($results[2]['roll']));
    }

    /**
     * Live CheckSpec, payload neutral fallback and selected profile keep precedence.
     *
     * @return void
     */
    public function testEfficiencyPrecedenceKeepsDimensionalRollSeparateFromRating(): void
    {
        $defaultEfficiency = 2;
        $captured = [];
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturnCallback(
            function () use (&$defaultEfficiency): CharacterRuleSlice {
                return $this->hitSlice($defaultEfficiency, 4);
            },
        );
        $mechanics = $this->createStub(IMechanics::class);
        $mechanics->method('getList')->willReturn([]);
        $rolls = $this->createMock(IMechanicRolls::class);
        $rolls->expects(self::exactly(4))
            ->method('roll')
            ->willReturnCallback(
                function (
                    RollSpec $spec,
                    Closure $rng,
                    array $bindings,
                    array $mechanicRecords,
                    ResolveActiveOptions $options,
                ) use (&$captured): RollResult {
                    $captured[] = $spec;

                    return new RollResult($spec, [], [], [], [], 0, null);
                },
            );
        $rolls->method('rate')->willReturn(new CheckRating(false, 0));
        $roll = new GameCheckRoll(
            $slices,
            $this->createStub(ICharacters::class),
            $mechanics,
            $rolls,
        );

        $roll->throwHitWithMastery(
            1,
            1,
            'hit',
            [],
            new DimensionalNumber(2, 1),
            null,
            ['base' => 0, 'size' => 0],
        );
        $roll->throwHitWithMastery(
            1,
            1,
            'hit',
            [],
            new DimensionalNumber(2, 1),
            new DimensionalNumber(5, 2),
            ['base' => 0, 'size' => 0],
        );

        $defaultEfficiency = null;
        $roll->throwHitWithMastery(
            1,
            1,
            'hit',
            [],
            new DimensionalNumber(2, 1),
            null,
            ['base' => 0, 'size' => 0],
        );

        $roll->throwHitWithMastery(
            1,
            1,
            'hit',
            [],
            new DimensionalNumber(2, 1),
            new DimensionalNumber(6, 0),
            ['base' => 0, 'size' => 0],
        );

        $resolved = (new RollSpec(2, 6, 3, 0))->withRollDefaults(
            new RollMechanicPayload(efficiency: 4, dieSize: 2),
        );

        self::assertSame(2, $captured[0]->getEfficiency());
        self::assertSame(1, $captured[0]->getDieSize());
        self::assertSame(5, $captured[1]->getEfficiency());
        self::assertSame(3, $captured[1]->getDieSize());
        self::assertSame(3, $captured[2]->getEfficiency());
        self::assertSame(1, $captured[2]->getDieSize());
        self::assertSame(6, $captured[3]->getEfficiency());
        self::assertSame(1, $captured[3]->getDieSize());
        self::assertSame(4, $resolved->getEfficiency());
        self::assertSame(2, $resolved->getDieSize());
    }

    /**
     * Явные преимущества действия попадают в бросок вместе с состоянием.
     *
     * @return void
     */
    public function testHitMergesExplicitAdvantages(): void
    {
        $captured = null;
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($this->hitSlice(null, 1));
        $mechanics = $this->createStub(IMechanics::class);
        $mechanics->method('getList')->willReturn([]);
        $rolls = $this->createStub(IMechanicRolls::class);
        $rolls->method('roll')->willReturnCallback(
            function (RollSpec $spec) use (&$captured): RollResult {
                $captured = $spec;

                return new RollResult($spec, [], [], [], [], 0, null);
            },
        );
        $rolls->method('rate')->willReturn(new CheckRating(false, 0));
        $roll = new GameCheckRoll(
            $slices,
            $this->createStub(ICharacters::class),
            $mechanics,
            $rolls,
        );

        $roll->throwHitWithMastery(
            1,
            1,
            'hit',
            [],
            new DimensionalNumber(2, 1),
            null,
            ['base' => 0, 'size' => 0],
            [new RollAdvantage('circumstances', -2)],
        );

        self::assertInstanceOf(RollSpec::class, $captured);
        self::assertCount(1, $captured->getAdvantages());
        self::assertSame('circumstances', $captured->getAdvantages()[0]->getSourceCode());
        self::assertSame(-2, $captured->getAdvantages()[0]->getDelta());
    }

    /**
     * Производная характеристика берёт min баз закупок, не строку себя.
     *
     * @return void
     */
    public function testDerivedCheckUsesMinOfPurchasedBases(): void
    {
        $captured = null;
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($this->derivedCheckSlice());
        $mechanics = $this->createStub(IMechanics::class);
        $mechanics->method('getList')->willReturn([]);
        $rolls = $this->createMock(IMechanicRolls::class);
        $rolls->method('roll')->willReturnCallback(
            function (RollSpec $spec) use (&$captured): RollResult {
                $captured = $spec;

                return new RollResult($spec, [], [], [], [], 0, null);
            },
        );
        $rolls->method('rate')->willReturn(new CheckRating(true, 1));
        $roll = new GameCheckRoll(
            $slices,
            $this->createStub(ICharacters::class),
            $mechanics,
            $rolls,
        );

        $roll->throwCheck(1, 1, 'watch', 'solo', [
            'characteristicPurchases' => [
                ['characteristicCode' => 'attention', 'value' => ['base' => 4, 'size' => 0]],
                ['characteristicCode' => 'reaction', 'value' => ['base' => 2, 'size' => 0]],
            ],
        ], null);

        self::assertInstanceOf(RollSpec::class, $captured);
        self::assertSame(2, $captured->getDiceCount());
    }

    /**
     * Пустой лист: производная берёт automatic базы операндов.
     *
     * @return void
     */
    public function testDerivedCheckUsesAutomaticBasesWhenPurchasesEmpty(): void
    {
        $captured = null;
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($this->derivedCheckSlice());
        $mechanics = $this->createStub(IMechanics::class);
        $mechanics->method('getList')->willReturn([]);
        $rolls = $this->createMock(IMechanicRolls::class);
        $rolls->method('roll')->willReturnCallback(
            function (RollSpec $spec) use (&$captured): RollResult {
                $captured = $spec;

                return new RollResult($spec, [], [], [], [], 0, null);
            },
        );
        $rolls->method('rate')->willReturn(new CheckRating(true, 1));
        $roll = new GameCheckRoll(
            $slices,
            $this->createStub(ICharacters::class),
            $mechanics,
            $rolls,
        );

        $roll->throwCheck(1, 1, 'watch', 'solo', ['characteristicPurchases' => []], null);

        self::assertInstanceOf(RollSpec::class, $captured);
        self::assertSame(3, $captured->getDiceCount());
    }

    /**
     * Строит live hit-check с roll payload.
     *
     * @param int|null $defaultEfficiency Efficiency CheckSpec.
     * @param int $payloadEfficiency Efficiency payload.
     *
     * @return CharacterRuleSlice Срез.
     */
    /**
     * Проверка на производное восприятие.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function derivedCheckSlice(): CharacterRuleSlice
    {
        $flags = [
            'damage_endurance' => false,
            'willpower' => false,
            'concentration_threshold' => false,
            'concentration_token' => false,
            'unstable_roll' => false,
            'initiative' => false,
            'dodge_soak' => false,
        ];

        return new CharacterRuleSlice(
            1,
            1,
            'world',
            [
                new CharacterResolvedRule($this->ruleRecord('attention', 'characteristic', 'Attention', [
                    ...$flags,
                    'automatic' => true,
                ]), []),
                new CharacterResolvedRule($this->ruleRecord('reaction', 'characteristic', 'Reaction', [
                    ...$flags,
                    'automatic' => true,
                ]), []),
                new CharacterResolvedRule($this->ruleRecord('perception', 'characteristic', 'Perception', [
                    ...$flags,
                    'formula' => 'min(attention, reaction)',
                ]), []),
                new CharacterResolvedRule($this->ruleRecord('watch', 'check', 'Watch', [
                    'allow_characteristic_override' => true,
                    'characteristic_code' => 'perception',
                    'allowed_modes' => 'both',
                    'ordinary_root' => true,
                    'concentration_token' => false,
                    'willpower' => false,
                    'unstable_check' => false,
                    'hit_check' => false,
                    'initiative' => true,
                    'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
                ], [[
                    'mechanic_id' => 5,
                    'mechanic_payload' => [
                        'type' => 'roll',
                        'data' => ['diceCount' => 1, 'dieFaces' => 6, 'efficiency' => 3],
                    ],
                ]]), []),
            ],
            [],
        );
    }

    /**
     * Живая версия правила.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param string $name Имя.
     * @param array<string, mixed> $spec Документ.
     * @param array<int, mixed> $mechanics Механики.
     *
     * @return RuleVersionRecord Версия.
     */
    private function ruleRecord(
        string $code,
        string $type,
        string $name,
        array $spec,
        array $mechanics = [],
    ): RuleVersionRecord {
        return new RuleVersionRecord(
            1,
            1,
            $code,
            true,
            $type,
            $name,
            '',
            $spec,
            [],
            $mechanics,
            'needs_work',
            '',
            DateTime::now(),
        );
    }

    /**
     * Строит live hit-check с roll payload.
     *
     * @param int|null $defaultEfficiency Efficiency CheckSpec.
     * @param int $payloadEfficiency Efficiency payload.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function hitSlice(?int $defaultEfficiency, int $payloadEfficiency): CharacterRuleSlice
    {
        $rule = new RuleVersionRecord(
            1,
            1,
            'hit',
            true,
            'check',
            'Hit',
            '',
            [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => true,
                'default_efficiency' => $defaultEfficiency,
                'concentration_token' => false,
                'willpower' => false,
                'unstable_check' => false,
                'hit_check' => true,
                'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
            ],
            [],
            [[
                'mechanic_id' => 5,
                'mechanic_payload' => [
                    'type' => 'roll',
                    'data' => [
                        'dieFaces' => 6,
                        'efficiency' => $payloadEfficiency,
                    ],
                ],
            ]],
            'needs_work',
            '',
            DateTime::now(),
        );

        return new CharacterRuleSlice(
            1,
            1,
            'world',
            [new CharacterResolvedRule($rule, [])],
            [],
        );
    }
}
