<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Service\GameActionFloor;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет пол стоимости действия: карточка, удар и оружие.
 */
final class GameActionFloorTest extends TestCase
{
    /**
     * Пол оружия объединяется с карточкой через max, а не суммой.
     *
     * @return void
     */
    public function testFloorMergesCardAndWeaponByMax(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(3);

        self::assertSame(5, $floor->floorOf($slice, 'swing', true, 5));
        self::assertSame(3, $floor->floorOf($slice, 'swing', true, 2));
        self::assertSame(3, $floor->floorOf($slice, 'swing', false, 0));
    }

    /**
     * Глобальный пол удара поднимает нулевую цену до двух.
     *
     * @return void
     */
    public function testStrikeFloorKeepsGlobalMinimum(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(null);

        self::assertSame(2, $floor->floorOf($slice, 'swing', true, 0));
        self::assertSame(4, $floor->floorOf($slice, 'swing', true, 4));
        self::assertSame(0, $floor->floorOf($slice, 'swing', false, 0));
    }

    /**
     * Пол оружия поднимает трату ОД, но не суммируется с ней.
     *
     * @return void
     */
    public function testApplyRaisesTurnSpendToWeaponFloor(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(3);
        $spends = [new ResourceSpend('ap', new ScalarResourceValue(1))];

        $raised = $floor->apply($slice, 'swing', $spends, true, 5);
        self::assertCount(1, $raised);
        self::assertSame('ap', $raised[0]->getResourceCode());
        self::assertSame(5, $raised[0]->getAmount()->toNative());

        $kept = $floor->apply($slice, 'swing', $spends, true, 0);
        self::assertSame(3, $kept[0]->getAmount()->toNative());
    }

    /**
     * Пол оружия поднимает карту цен без добавления второго ресурса.
     *
     * @return void
     */
    public function testAmountsRaisesOnlyTurnResource(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(3);

        $amounts = $floor->amounts($slice, 'swing', ['ap' => 1, 'other' => 4], false, 5);
        self::assertSame(5, $amounts['ap']);
        self::assertSame(4, $amounts['other']);
    }

    /**
     * Собирает срез с карточкой действия и turn-refill ресурсом.
     *
     * @param int|null $cardMin Пол карточки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(?int $cardMin): CharacterRuleSlice
    {
        $action = new RuleVersionRecord(
            1,
            1,
            'swing',
            true,
            'ability',
            'Swing',
            '',
            [
                'type' => 'action',
                'min_total_action_cost' => $cardMin,
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'ap',
                    'amount' => 1,
                ]],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );
        $resource = new RuleVersionRecord(
            2,
            2,
            'ap',
            true,
            'resource',
            'AP',
            '',
            [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'turn_refill' => true,
                'limit' => ['base' => 4, 'adjustments' => []],
            ],
            [],
            [],
            'needs_work',
            '',
            DateTime::now(),
        );

        return new CharacterRuleSlice(
            1,
            1,
            'world',
            [new CharacterResolvedRule($action, []), new CharacterResolvedRule($resource, [])],
            [],
        );
    }
}
