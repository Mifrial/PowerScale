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
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameCheckTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameInitiativeMysqlTest extends TestCase
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
     * Нет карточки или их две — порядок не пишется.
     *
     * @return void
     */
    public function testMissingOrDuplicateInitiativeDoesNotStoreOrder(): void
    {
        $world = $this->worldWithInitiative(false);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->battle($gameId, [$hero]);
        $missing = $this->roll($gameId, $battleId, 'none', 1);
        self::assertSame('GAME_INVALID', $missing['error']['code']);
        self::assertNull($this->turnOrder($battleId));
    }

    /**
     * Карточка только с попаданием не становится инициативой.
     *
     * @return void
     */
    public function testHitCheckDoesNotStoreTurnOrder(): void
    {
        $world = $this->worldWithInitiative(false, false, true);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->battle($gameId, [$hero]);
        $rolled = $this->roll($gameId, $battleId, 'hit', 1);
        self::assertSame('GAME_INVALID', $rolled['error']['code']);
        self::assertNull($this->turnOrder($battleId));
    }

    /**
     * Две живые карточки с признаком не пишут порядок.
     *
     * @return void
     */
    public function testTwoInitiativeChecksDoNotStoreOrder(): void
    {
        $world = $this->worldWithInitiative(true, true);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->battle($gameId, [$hero]);
        $double = $this->roll($gameId, $battleId, 'two', 1);
        self::assertSame('GAME_INVALID', $double['error']['code']);
        self::assertNull($this->turnOrder($battleId));
    }

    /**
     * Ровно одна карточка сортирует по успехам и не пишет проверку.
     *
     * @return void
     */
    public function testOneInitiativeCheckStoresOrderOnBattle(): void
    {
        $world = $this->worldWithInitiative(true);
        $gameId = $this->addGame($world->getId());
        $firstId = $this->admit($world->getId(), $gameId, 'Hero');
        $secondId = $this->admit($world->getId(), $gameId, 'Ally');
        $actual = $this->characterFacade()->get($firstId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->battle($gameId, [$firstId, $secondId]);
        self::assertNull($this->turnOrder($battleId));
        $refused = $this->dispatch('game.rollInitiative', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'order',
            'expectedVersion' => 1,
            'order' => [],
        ]);
        self::assertSame('INVALID_PARAMS', $refused['error']['code']);
        self::assertNull($this->turnOrder($battleId));
        $stale = $this->roll($gameId, $battleId, 'stale', 2);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        $rolled = $this->roll($gameId, $battleId, 'order', 1);
        self::assertTrue($rolled['success']);
        self::assertSame(2, $rolled['data']['version']);
        self::assertSame($this->bases($rolled['data']['order']), $this->sortedBases($rolled['data']['order']));
        self::assertCount(2, $rolled['data']['order']);
        self::assertSame(0, $this->countAll(GameCheckTable::class));
        self::assertSame($actual, $this->characterFacade()->get($firstId)->getActualVersion());
        self::assertSame(1, $this->sessionVersion($gameId));
        $replay = $this->roll($gameId, $battleId, 'order', 1);
        self::assertSame($rolled['data'], $replay['data']);
        $again = $this->roll($gameId, $battleId, 'again', 2);
        self::assertTrue($again['success']);
        self::assertSame(3, $again['data']['version']);
        self::assertSame($this->bases($again['data']['order']), $this->sortedBases($again['data']['order']));
    }

    /**
     * Смена состава сохраняет оставшихся и ставит новых в конец.
     *
     * @return void
     */
    public function testRosterKeepsOrderAndAppendsJoined(): void
    {
        $world = $this->worldWithInitiative(true);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $ally = $this->admit($world->getId(), $gameId, 'Ally');
        $third = $this->admit($world->getId(), $gameId, 'Third');
        $fourth = $this->admit($world->getId(), $gameId, 'Fourth');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->battle($gameId, [$hero, $ally]);
        $before = $this->dispatch('game.setBattleRoster', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'plain',
            'participants' => [['type' => 'character', 'id' => $hero]],
            'expectedVersion' => 1,
        ]);
        self::assertTrue($before['success']);
        self::assertArrayNotHasKey('order', $before['data']);
        self::assertNull($this->turnOrder($battleId));
        $this->dispatch('game.setBattleRoster', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'back',
            'participants' => [
                ['type' => 'character', 'id' => $hero],
                ['type' => 'character', 'id' => $ally],
            ],
            'expectedVersion' => 2,
        ]);
        $rolled = $this->roll($gameId, $battleId, 'order', 3);
        self::assertTrue($rolled['success']);
        $keptId = $rolled['data']['order'][0]['id'];
        $droppedId = $rolled['data']['order'][1]['id'];
        $trimmed = $this->roster($gameId, $battleId, 'trim', 4, [$keptId]);
        self::assertSame([$keptId], array_column($trimmed['data']['order'], 'id'));
        $returned = $this->roster($gameId, $battleId, 'return', 5, [$keptId, $droppedId]);
        $returnedIds = array_column($returned['data']['order'], 'id');
        self::assertSame($keptId, $returnedIds[0]);
        self::assertSame($droppedId, $returnedIds[1]);
        self::assertNull($returned['data']['order'][1]['roll']);
        $pair = $this->roster($gameId, $battleId, 'pair', 6, [$keptId, $third, $fourth]);
        self::assertSame($keptId, $pair['data']['order'][0]['id']);
        $tail = array_slice($pair['data']['order'], 1);
        self::assertEqualsCanonicalizing([$third, $fourth], array_column($tail, 'id'));
        self::assertSame($this->bases($tail), $this->sortedBases($tail));
    }

    /**
     * Конец снимает порядок одного боя и сессию не гасит.
     *
     * @return void
     */
    public function testEndBattleDropsOrderAndLeavesSession(): void
    {
        $world = $this->worldWithInitiative(true);
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $ally = $this->admit($world->getId(), $gameId, 'Ally');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $first = $this->battle($gameId, [$hero]);
        $second = $this->battle($gameId, [$ally], 'other');
        $this->roll($gameId, $first, 'one', 1);
        $kept = $this->roll($gameId, $second, 'two', 1);
        self::assertTrue($kept['success']);
        $ended = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $first,
            'idempotencyKey' => 'end',
            'expectedVersion' => 2,
        ]);
        self::assertTrue($ended['success']);
        self::assertTrue($this->dispatch('game.get', ['id' => $gameId])['data']['sessionRunning']);
        self::assertSame(1, $this->countAll(GameSessionTable::class));
        $stored = $this->turnOrder($second);
        self::assertIsArray($stored);
        self::assertSame(array_column($kept['data']['order'], 'id'), array_column($stored, 'id'));
        $fresh = $this->battle($gameId, [$hero], 'fresh');
        self::assertNull($this->turnOrder($fresh));
    }

    /**
     * Мир с правилом броска и нулём, одной или двумя карточками инициативы.
     *
     * @param bool $initiative Первая карточка.
     * @param bool $second Вторая карточка.
     * @param bool $hitCheck Признак попадания вместо инициативы на первой карточке.
     *
     * @return \Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord Мир.
     */
    private function worldWithInitiative(
        bool $initiative,
        bool $second = false,
        bool $hitCheck = false,
    ): \Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $mechanics->add('roll', 'Бросок', '', '1.0.0');
        $world = $this->addWorldWithRevision('razrabotka');
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => true,
            'concentration_token' => false,
            'willpower' => false,
            'unstable_check' => false,
            'hit_check' => $hitCheck,
            'initiative' => $initiative,
            'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
        ];
        $entries = [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => [
                    'diceCount' => 1,
                    'dieFaces' => 6,
                    'efficiency' => 3,
                ]],
            ]], 'needs_work')),
            RuleCommitEntry::put('watch', new RuleVersionBody('check', 'Watch', '', $check, [], [], 'needs_work')),
        ];
        if ($second) {
            $entries[] = RuleCommitEntry::put('alert', new RuleVersionBody('check', 'Alert', '', [
                ...$check,
                'initiative' => true,
                'ordinary_root' => false,
            ], [], [], 'needs_work'));
        }

        $this->gameRuleSpaces()->commit($world->getId(), $entries);

        return $world;
    }

    /**
     * Допущенный active.
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
     * Черновик на ревизии коммита.
     *
     * @param int $spaceId Мир.
     *
     * @return int Игра.
     */
    private function addGame(int $spaceId): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 2,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
    }

    /**
     * Черновик персонажа.
     *
     * @param int $spaceId Мир.
     * @param string $name Имя.
     *
     * @return int Персонаж.
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
     * Открытый бой.
     *
     * @param int $gameId Игра.
     * @param list<int> $characterIds Состав.
     * @param string $key Ключ.
     *
     * @return int Бой.
     */
    private function battle(int $gameId, array $characterIds, string $key = 'battle'): int
    {
        $participants = [];
        foreach ($characterIds as $characterId) {
            $participants[] = ['type' => 'character', 'id' => $characterId];
        }

        $started = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => $key,
            'participants' => $participants,
        ]);
        self::assertTrue($started['success']);

        return $started['data']['battleId'];
    }

    /**
     * Команда инициативы.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $key Ключ.
     * @param int $version Версия.
     *
     * @return array<string, mixed> Конверт.
     */
    private function roll(int $gameId, int $battleId, string $key, int $version): array
    {
        return $this->dispatch('game.rollInitiative', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key,
            'expectedVersion' => $version,
        ]);
    }

    /**
     * Смена состава.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $key Ключ.
     * @param int $version Версия.
     * @param list<int> $characterIds Состав.
     *
     * @return array<string, mixed> Конверт.
     */
    private function roster(int $gameId, int $battleId, string $key, int $version, array $characterIds): array
    {
        $participants = [];
        foreach ($characterIds as $characterId) {
            $participants[] = ['type' => 'character', 'id' => $characterId];
        }

        $changed = $this->dispatch('game.setBattleRoster', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key,
            'participants' => $participants,
            'expectedVersion' => $version,
        ]);
        self::assertTrue($changed['success'], json_encode($changed));

        return $changed;
    }

    /**
     * Колонка порядка.
     *
     * @param int $battleId Бой.
     *
     * @return array<int, mixed>|null JSON.
     */
    private function turnOrder(int $battleId): ?array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = $gateway->open(GameBattleTable::class)->records()->getById($battleId);
        self::assertIsArray($row);

        return $row['turn_order'];
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
     * Версия сессии.
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
     * Успехи записей.
     *
     * @param array<int, array<string, mixed>> $order Порядок.
     *
     * @return list<int> Числа.
     */
    private function bases(array $order): array
    {
        $bases = [];
        foreach ($order as $entry) {
            self::assertIsArray($entry['roll']);
            $bases[] = $entry['roll']['base'];
        }

        return $bases;
    }

    /**
     * Те же успехи по убыванию.
     *
     * @param array<int, array<string, mixed>> $order Порядок.
     *
     * @return list<int> Числа.
     */
    private function sortedBases(array $order): array
    {
        $bases = $this->bases($order);
        rsort($bases);

        return $bases;
    }
}
