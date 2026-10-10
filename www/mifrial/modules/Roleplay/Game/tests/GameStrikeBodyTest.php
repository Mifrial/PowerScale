<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Service\GameStrikeBody;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет строгий transport-контракт выбора instance и block profile.
 */
final class GameStrikeBodyTest extends TestCase
{
    /**
     * Inventory id обязателен для атаки.
     *
     * @return void
     */
    public function testAttackRequiresInventoryId(): void
    {
        $this->expectException(ActionException::class);

        (new GameStrikeBody())->attack([
            'attacker' => ['type' => 'character', 'id' => 1],
            'defender' => ['type' => 'npc', 'id' => 2],
            'actionRuleCode' => 'swing',
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ]);
    }

    /**
     * Attack transport accepts an untrusted chosen amount proposal.
     *
     * @return void
     */
    public function testAttackAcceptsChosenAmounts(): void
    {
        $choice = (new GameStrikeBody())->attack([
            'attacker' => ['type' => 'character', 'id' => 1],
            'defender' => ['type' => 'npc', 'id' => 2],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 7,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
            'chosenAmounts' => ['action-points' => 2],
        ]);

        self::assertSame(['action-points' => 2], $choice['chosenAmounts']);
    }

    /**
     * Block transport сохраняет выбранный instance и profile index.
     *
     * @return void
     */
    public function testBlockRequiresSelectedInstanceAndProfile(): void
    {
        $choice = (new GameStrikeBody())->defense([
            'reaction' => 'block',
            'blockItemInventoryId' => 7,
            'blockItemProfileIndex' => 0,
        ]);

        self::assertSame(7, $choice['blockItemInventoryId']);
        self::assertSame(0, $choice['blockItemProfileIndex']);
        self::assertNull($choice['blockItemRuleCode']);
    }
}
