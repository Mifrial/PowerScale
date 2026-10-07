<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameBattleCommandTable;
use Mifrial\Roleplay\Game\Table\GameBattleParticipantTable;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameSessionCharacterTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;

final class GameBattleMysqlTest extends TestCase
{
    use GameMysqlFixture;

    private ?IRequestContext $requestContext = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectGameMysql();
        $requestContext = $this->gameApplication()->getLocator()->get(IKernelContainer::class)->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
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
     * Два боя, конец одного, лист и версия сессии прежние.
     *
     * @return void
     */
    public function testTwoBattlesEndOneAndLeaveSession(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId(), 2);
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        $sheet = $this->characterFacade()->get($characterId)->getSheet();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]);
        self::assertTrue($npc['success']);
        $npcVersion = $npc['data']['version'];
        $npcActual = $npc['data']['actualVersion'];
        self::assertSame(0, $this->countWhere(GameSessionCharacterTable::class, 'character_id', $characterId));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        self::assertSame(1, $this->countWhere(GameSessionCharacterTable::class, 'character_id', $characterId));
        $first = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [['type' => 'character', 'id' => $characterId]],
        ]);
        $second = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b2',
            'participants' => [['type' => 'npc', 'id' => $npc['data']['npcId']]],
        ]);
        self::assertTrue($first['success']);
        self::assertTrue($second['success']);
        self::assertNotSame($first['data']['battleId'], $second['data']['battleId']);
        self::assertSame(1, $first['data']['version']);
        self::assertFalse($first['data']['ended']);
        self::assertSame(2, $this->countAll(GameBattleTable::class));
        self::assertSame(1, $this->sessionVersion($gameId));
        $ended = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $first['data']['battleId'],
            'idempotencyKey' => 'e1',
            'expectedVersion' => 1,
        ]);
        self::assertTrue($ended['success']);
        self::assertTrue($ended['data']['ended']);
        self::assertSame($first['data']['battleId'], $ended['data']['battleId']);
        self::assertSame(1, $this->countAll(GameBattleTable::class));
        self::assertSame(1, $this->sessionVersion($gameId));
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($card['data']['sessionRunning']);
        self::assertSame('draft', $card['data']['status']);
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame($sheet, $this->characterFacade()->get($characterId)->getSheet());
        $npcRow = $this->dispatch('game.getNpc', ['gameId' => $gameId, 'npcId' => $npc['data']['npcId']]);
        self::assertSame($npcActual, $npcRow['data']['actualVersion']);
        self::assertSame($npcVersion, $npcRow['data']['version']);
        self::assertSame(1, $this->countWhere(GameSessionCharacterTable::class, 'character_id', $characterId));
    }

    /**
     * CAS, повтор ключа и состав.
     *
     * @return void
     */
    public function testVersionRosterAndIdempotency(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId(), 2);
        $inside = $this->admit($world->getId(), $gameId, 'In');
        $outsideId = $this->addCharacter($world->getId(), 'Out');
        $this->membershipFacade()->submit($gameId, $outsideId, $this->ownerUserId);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $started = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'same',
            'participants' => [['type' => 'character', 'id' => $inside]],
        ]);
        $replay = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'same',
            'participants' => [['type' => 'character', 'id' => $inside]],
        ]);
        self::assertSame($started['data'], $replay['data']);
        self::assertSame(1, $this->countAll(GameBattleTable::class));
        $other = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'same',
            'participants' => [],
        ]);
        self::assertSame('GAME_CONFLICT', $other['error']['code']);
        $stale = $this->dispatch('game.setBattleRoster', [
            'gameId' => $gameId,
            'battleId' => $started['data']['battleId'],
            'idempotencyKey' => 'roster',
            'participants' => [],
            'expectedVersion' => 2,
        ]);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        self::assertSame(1, $this->countWhere(GameBattleParticipantTable::class, 'subject_id', $inside));
        $cleared = $this->dispatch('game.setBattleRoster', [
            'gameId' => $gameId,
            'battleId' => $started['data']['battleId'],
            'idempotencyKey' => 'roster',
            'participants' => [],
            'expectedVersion' => 1,
        ]);
        self::assertSame(2, $cleared['data']['version']);
        self::assertSame($started['data']['battleId'], $cleared['data']['battleId']);
        self::assertSame(0, $this->countWhere(GameBattleParticipantTable::class, 'subject_id', $inside));
        $missing = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'out',
            'participants' => [['type' => 'character', 'id' => $outsideId]],
        ]);
        self::assertSame('GAME_NOT_FOUND', $missing['error']['code']);
        $dup = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'dup',
            'participants' => [
                ['type' => 'character', 'id' => $inside],
                ['type' => 'character', 'id' => $inside],
            ],
        ]);
        self::assertSame('GAME_INVALID', $dup['error']['code']);
        $unknownNpc = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'npc',
            'participants' => [['type' => 'npc', 'id' => 999]],
        ]);
        self::assertSame('GAME_NOT_FOUND', $unknownNpc['error']['code']);
        $staleEnd = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $started['data']['battleId'],
            'idempotencyKey' => 'end',
            'expectedVersion' => 1,
        ]);
        self::assertSame('GAME_CONFLICT', $staleEnd['error']['code']);
        $ended = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $started['data']['battleId'],
            'idempotencyKey' => 'end',
            'expectedVersion' => 2,
        ]);
        self::assertTrue($ended['data']['ended']);
        $endReplay = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $started['data']['battleId'],
            'idempotencyKey' => 'end',
            'expectedVersion' => 2,
        ]);
        self::assertSame($ended['data'], $endReplay['data']);
        self::assertSame(1, $this->sessionVersion($gameId));
    }

    /**
     * Stop снимает бои и команды. Права и completed.
     *
     * @return void
     */
    public function testStopDeletesBattlesAndPermissions(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId(), 2);
        $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $started = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'live',
            'participants' => [],
        ]);
        self::assertTrue($started['success']);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'pl', 'name' => 'Pl']);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $playerId,
            'role' => 'player',
        ]));
        $this->gameFacade()->update($gameId, GamePatch::fromNormalized($this->card($world->getId(), $gameId, [
            'status' => 'in_process',
            'rulesRevision' => 2,
        ])));
        $this->setActor($playerId, []);
        $denied = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'player',
            'participants' => [],
        ]);
        self::assertSame('AUTH_DENIED', $denied['error']['code']);
        $seen = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'live',
            'participants' => [],
        ]);
        self::assertTrue($seen['success']);
        self::assertSame($started['data']['battleId'], $seen['data']['battleId']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $stopped = $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        self::assertTrue($stopped['success']);
        self::assertFalse($stopped['data']['sessionRunning']);
        self::assertSame('in_process', $stopped['data']['status']);
        self::assertSame(0, $this->countAll(GameBattleTable::class));
        self::assertSame(0, $this->countAll(GameBattleCommandTable::class));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $again = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'live',
            'participants' => [],
        ]);
        self::assertTrue($again['success']);
        self::assertNotSame($started['data']['battleId'], $again['data']['battleId']);
        $idle = $this->addGame($world->getId(), 2);
        $noSession = $this->dispatch('game.startBattle', [
            'gameId' => $idle,
            'idempotencyKey' => 'none',
            'participants' => [],
        ]);
        self::assertSame('GAME_INVALID', $noSession['error']['code']);
        $this->gameFacade()->update($idle, GamePatch::fromNormalized($this->card($world->getId(), $idle, [
            'status' => 'completed',
        ])));
        $closed = $this->dispatch('game.startBattle', [
            'gameId' => $idle,
            'idempotencyKey' => 'done',
            'participants' => [],
        ]);
        self::assertSame('GAME_INVALID', $closed['error']['code']);
        $draftId = $this->addGame($world->getId(), 2);
        $stranger = $this->gameUserAccounts()->addFromInput(['login' => 'st', 'name' => 'St']);
        $this->setActor($stranger, []);
        $hidden = $this->dispatch('game.startBattle', [
            'gameId' => $draftId,
            'idempotencyKey' => 'live',
            'participants' => [],
        ]);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
    }

    /**
     * Мир с расой на второй ревизии.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithRace(): RuleSpaceRecord
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
        ]);

        return $world;
    }

    /**
     * Допущенный active на ревизии игры.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param string $name Имя.
     *
     * @return int Персонаж.
     */
    private function admit(int $spaceId, int $gameId, string $name): int
    {
        $characterId = $this->addCharacter($spaceId, $name);
        $this->characterFacade()->replaceMigrated($characterId, $name, true, $this->storedChoices($name), [], 2, 1);
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    /**
     * Черновик.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return int Игра.
     */
    private function addGame(int $spaceId, int $rulesRevision): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => $rulesRevision,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
    }

    /**
     * Персонаж фикстуры.
     *
     * @param int $spaceId Мир.
     * @param string $name Имя.
     *
     * @return int Id.
     */
    private function addCharacter(int $spaceId, string $name): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => $name,
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
    }

    /**
     * Выборы листа.
     *
     * @param string $name Имя.
     *
     * @return array<string, mixed> choices.
     */
    private function storedChoices(string $name): array
    {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => [],
            'inventory' => [],
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'active' => true,
        ];
    }

    /**
     * Тело update.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param array<string, mixed> $overrides Поля.
     *
     * @return array<string, mixed> JSON.
     */
    private function card(int $spaceId, int $gameId, array $overrides): array
    {
        return array_merge([
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ], $overrides);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $permissionKeys, false));
    }

    /**
     * Action.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }

    /**
     * Версия строки сессии.
     *
     * @param int $gameId Игра.
     *
     * @return int Счётчик.
     */
    private function sessionVersion(int $gameId): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $result = $gateway->open(GameSessionTable::class)->records()->getList(new ListQuery(
            new FilterGroup('AND', [new FilterCondition('game_id', '=', $gameId)]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));
        $row = $result->rows()[0] ?? null;
        self::assertIsArray($row);

        return $row['state_version'];
    }

    /**
     * Число строк таблицы.
     *
     * @param class-string $table Класс.
     *
     * @return int Число.
     */
    private function countAll(string $table): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $result = $gateway->open($table)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));

        return count($result->rows());
    }

    /**
     * Число строк по равенству.
     *
     * @param class-string $table Класс.
     * @param string $column Колонка.
     * @param int $value Значение.
     *
     * @return int Число.
     */
    private function countWhere(string $table, string $column, int $value): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $result = $gateway->open($table)->records()->getList(new ListQuery(
            new FilterGroup('AND', [new FilterCondition($column, '=', $value)]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));

        return count($result->rows());
    }
}
