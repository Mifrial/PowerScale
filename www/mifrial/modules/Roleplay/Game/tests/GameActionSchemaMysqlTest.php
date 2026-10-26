<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Setup\AlignGameActionColumnsStep;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use PHPUnit\Framework\TestCase;

/**
 * Setup приводит заголовок действия к карте без полей предмета.
 */
final class GameActionSchemaMysqlTest extends TestCase
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
     * Старые NOT NULL колонки заголовка снимаются, новая вставка проходит.
     *
     * @return void
     */
    public function testAlignDropsLegacyHeaderColumns(): void
    {
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        foreach (['item_inventory_id', 'item_rule_code', 'profile_type', 'profile_index'] as $column) {
            $mysql->statement('ALTER TABLE game_action ADD COLUMN ' . $column . ' INT NOT NULL');
        }

        $gateway = $this->smartTableGateway();
        (new AlignGameActionColumnsStep($gateway))->run();

        $columns = array_map(
            static fn (object $row): string => (string) $row->Field,
            $mysql->select('SHOW COLUMNS FROM game_action'),
        );
        self::assertNotContains('item_inventory_id', $columns);
        self::assertNotContains('item_rule_code', $columns);
        self::assertNotContains('profile_type', $columns);
        self::assertNotContains('profile_index', $columns);
        self::assertContains('open', $columns);

        $world = $this->addWorldWithRevision('align');
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Align',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $world->getId(),
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
        $sessionId = $gateway->open(GameSessionTable::class)->records()->add([
            'game_id' => $gameId,
            'status' => 'active',
            'state_version' => 1,
        ]);
        $mysql->statement(
            'INSERT INTO game_action'
            . ' (kind, battle_id, session_id, attacker_kind, attacker_id, action_rule_code, open)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['strike', 1, $sessionId, 'npc', 1, 'swing', 1],
        );
        self::assertSame(1, (int) $mysql->select('SELECT COUNT(*) AS c FROM game_action')[0]->c);
    }

    /**
     * Шлюз таблиц приложения.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        $gateway = $this->gameApplication()
            ->getLocator()
            ->get(ISmartTableContainer::class)
            ->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return $gateway;
    }
}
