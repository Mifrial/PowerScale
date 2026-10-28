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
 * Проверяет пол стоимости действия: карточка и глобальный пол удара.
 */
final class GameActionFloorTest extends TestCase
{
    /**
     * Карточный пол поднимает цену, но не опускает её.
     *
     * @return void
     */
    public function testCardFloorRaisesWithoutLowering(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(3);

        self::assertSame(3, $floor->floorOf($slice, 'swing', true));
        self::assertSame(3, $floor->floorOf($slice, 'swing', false));
        self::assertSame(0, $floor->floorOf($this->slice(null), 'swing', false));
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

        self::assertSame(2, $floor->floorOf($slice, 'swing', true));
        self::assertSame(2, $floor->floorOf($this->slice(1), 'swing', true));
        self::assertSame(0, $floor->floorOf($slice, 'swing', false));
    }

    /**
     * Пол поднимает трату ОД, но не суммируется с ней.
     *
     * @return void
     */
    public function testApplyRaisesTurnSpendToCardFloor(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(3);
        $spends = [new ResourceSpend('ap', new ScalarResourceValue(1))];

        $raised = $floor->apply($slice, 'swing', $spends, true);
        self::assertCount(1, $raised);
        self::assertSame('ap', $raised[0]->getResourceCode());
        self::assertSame(3, $raised[0]->getAmount()->toNative());

        $kept = $floor->apply($slice, 'swing', [new ResourceSpend('ap', new ScalarResourceValue(4))], true);
        self::assertSame(4, $kept[0]->getAmount()->toNative());
    }

    /**
     * Пол поднимает карту цен без добавления второго ресурса.
     *
     * @return void
     */
    public function testAmountsRaisesOnlyTurnResource(): void
    {
        $floor = new GameActionFloor();
        $slice = $this->slice(3);

        $amounts = $floor->amounts($slice, 'swing', ['ap' => 1, 'other' => 4], false);
        self::assertSame(3, $amounts['ap']);
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
