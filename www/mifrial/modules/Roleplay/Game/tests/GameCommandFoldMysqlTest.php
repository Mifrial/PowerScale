<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Service\GameDeliveryPortFactory;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use PHPUnit\Framework\TestCase;

final class GameCommandFoldMysqlTest extends TestCase
{
    use GameMysqlFixture;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectGameMysql();
    }

    /**
     * Снимает таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropGameTables();
    }

    /**
     * Догон wide пишет outbox как strike. Check в outbox не попадает.
     *
     * @return void
     */
    public function testCatchUpWritesWideAsStrikeAndSkipsCheck(): void
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $world = $this->addWorldWithRevision('catch');
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Catch',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $world->getId(),
            'rulesRevision' => 1,
        ]));
        $sessionId = $gateway->open(GameSessionTable::class)->records()->add([
            'game_id' => $gameId,
            'status' => 'open',
            'state_version' => 1,
        ]);
        $commands = new GameCommandRepository($gateway);
        $wideId = $commands->reserve($gameId, $sessionId, 'wide_strike', 'w-catch', ['battleId' => 4]);
        $commands->complete($wideId, ['battleId' => 4]);
        $checkId = $commands->reserve($gameId, $sessionId, 'check', 'c-catch', []);
        $commands->complete($checkId, []);
        $delivery = (new GameDeliveryPortFactory())->create($this->gameApplication()->getLocator());
        $delivery->catchUp($gameId);
        $rows = $gateway->open(GameDeliveryTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows();
        self::assertCount(1, $rows);
        self::assertIsArray($rows[0]);
        self::assertSame('strike', $rows[0]['source']);
        self::assertSame($wideId, $rows[0]['source_id']);
        $frame = $delivery->tail($gameId, $this->ownerUserId, true, 0);
        self::assertSame(4, $frame['events'][0]['battleId'] ?? null);
    }
}
