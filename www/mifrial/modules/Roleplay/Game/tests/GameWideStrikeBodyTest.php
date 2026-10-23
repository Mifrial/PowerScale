<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Service\GameStrikeBody;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет transport широкого ranged strike.
 */
final class GameWideStrikeBodyTest extends TestCase
{
    /**
     * Широкий транспорт принимает одну цель: потолок задаёт карточка.
     *
     * @return void
     */
    public function testSingleTargetIsAccepted(): void
    {
        $choice = (new GameStrikeBody())->wideAttack([
            'attacker' => ['type' => 'character', 'id' => 1],
            'targets' => [['type' => 'npc', 'id' => 2]],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 7,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ]);

        self::assertSame([['type' => 'npc', 'id' => 2]], $choice['targets']);
    }

    /**
     * Пустой список целей отклоняется.
     *
     * @return void
     */
    public function testEmptyTargetsAreRejected(): void
    {
        $this->expectException(ActionException::class);

        (new GameStrikeBody())->wideAttack([
            'attacker' => ['type' => 'character', 'id' => 1],
            'targets' => [],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 7,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ]);
    }

    /**
     * Shoot wide transport сохраняет selected ammunition instance.
     *
     * @return void
     */
    public function testShootRequiresAndKeepsAmmunitionInventoryId(): void
    {
        $choice = (new GameStrikeBody())->wideAttack([
            'attacker' => ['type' => 'character', 'id' => 1],
            'targets' => [
                ['type' => 'npc', 'id' => 2],
                ['type' => 'npc', 'id' => 3],
            ],
            'actionRuleCode' => 'shoot',
            'itemInventoryId' => 7,
            'itemRuleCode' => 'bow',
            'profileType' => 'shoot',
            'profileIndex' => 0,
            'ammunitionInventoryId' => 8,
        ]);

        self::assertSame(8, $choice['ammunitionInventoryId']);
    }

    /**
     * Wide strike transport отклоняет ammunition identity для melee.
     *
     * @return void
     */
    public function testStrikeRejectsAmmunitionInventoryId(): void
    {
        $this->expectException(ActionException::class);

        (new GameStrikeBody())->wideAttack([
            'attacker' => ['type' => 'character', 'id' => 1],
            'targets' => [
                ['type' => 'npc', 'id' => 2],
                ['type' => 'npc', 'id' => 3],
            ],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 7,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
            'ammunitionInventoryId' => 8,
        ]);
    }
}
