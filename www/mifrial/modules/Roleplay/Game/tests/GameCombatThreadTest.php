<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Service\GameCombatThread;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет привязку тредов атак к текущему ходу боя.
 */
final class GameCombatThreadTest extends TestCase
{
    /**
     * Атака вкладывается в ход текущего раунда и активного участника.
     *
     * @return void
     */
    public function testAttackThreadUsesCurrentTurnAsParent(): void
    {
        $battle = new GameBattleRecord(52, 7, 3, [], [], 2, 4);

        self::assertSame(
            ['id' => 'attack-9', 'parentId' => 'b52-r4-t2', 'kind' => 'attack'],
            GameCombatThread::buildAttack($battle, 'attack-9'),
        );
    }

    /**
     * До расстановки курсора атака привязывается к первому ходу первого раунда.
     *
     * @return void
     */
    public function testAttackThreadFallsBackToFirstTurn(): void
    {
        $battle = new GameBattleRecord(52, 7, 3, [], [], 0, 0);

        self::assertSame('b52-r1-t0', GameCombatThread::buildAttack($battle, 'attack-wide-3')['parentId']);
    }
}
