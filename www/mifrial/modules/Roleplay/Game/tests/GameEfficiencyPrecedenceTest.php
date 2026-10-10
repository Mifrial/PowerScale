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
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет boundary precedence явной эффективности CheckSpec.
 */
final class GameEfficiencyPrecedenceTest extends TestCase
{
    /**
     * Явная эффективность 3 не подменяется payload efficiency.
     *
     * @return void
     */
    public function testExplicitNeutralCheckEfficiencyBeatsPayload(): void
    {
        $captured = null;
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($this->hitSlice(3));
        $mechanics = $this->createStub(IMechanics::class);
        $mechanics->method('getList')->willReturn([]);
        $rolls = $this->createMock(IMechanicRolls::class);
        $rolls->expects(self::once())
            ->method('roll')
            ->willReturnCallback(
                function (
                    RollSpec $spec,
                    Closure $rng,
                    array $bindings,
                    array $mechanicRecords,
                    ResolveActiveOptions $options,
                ) use (&$captured): RollResult {
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
        );

        self::assertInstanceOf(RollSpec::class, $captured);
        self::assertSame(3, $captured->getEfficiency());
        self::assertTrue($captured->isEfficiencyExplicit());
    }

    /**
     * Строит live hit-check с payload efficiency.
     *
     * @param int|null $defaultEfficiency Efficiency CheckSpec.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function hitSlice(?int $defaultEfficiency): CharacterRuleSlice
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
                        'efficiency' => 2,
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
