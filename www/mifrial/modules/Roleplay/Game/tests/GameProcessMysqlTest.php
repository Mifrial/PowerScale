<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Game\Interface\Service\IGameProcesses;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameProcessTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;

final class GameProcessMysqlTest extends TestCase
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
     * open и resolve не пишут лист.
     *
     * @return void
     */
    public function testOpenAndResolveDoNotWriteSheet(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $sheet = $this->characterFacade()->get($characterId)->getSheet();
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [['type' => 'character', 'id' => $characterId]],
        ]);
        self::assertTrue($battle['success']);
        $session = $this->processes()->open($gameId, null, 'character', $characterId);
        $fight = $this->processes()->open($gameId, $battle['data']['battleId'], 'character', $characterId);
        self::assertSame('open', $session['status']);
        self::assertNull($session['battleId']);
        self::assertSame($battle['data']['battleId'], $fight['battleId']);
        self::assertSame('resolved', $this->processes()->resolve($session['processId'])['status']);
        self::assertSame($sheet, $this->characterFacade()->get($characterId)->getSheet());
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame(['id', 'session_id', 'battle_id', 'participant_type', 'participant_id', 'status'], array_keys($this->rows()[0]));
        $missing = $this->processes();
        $this->expectException(GameNotFoundException::class);
        $missing->resolve(999999);
    }

    /**
     * Чужой участник и чужой бой не пишут строку.
     *
     * @return void
     */
    public function testOpenRejectsOutsider(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $inside = $this->admit($world->getId(), $gameId, 'Hero');
        $outside = $this->addCharacter($world->getId(), 'Other');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        try {
            $this->processes()->open($gameId, null, 'character', $outside);
            self::fail('outsider');
        } catch (GameNotFoundException $exception) {
            self::assertSame('GAME_NOT_FOUND', $exception->getErrorCode());
        }

        self::assertSame([], $this->rows());
        $this->expectException(GameNotFoundException::class);
        $this->processes()->open($gameId, 999999, 'character', $inside);
    }

    /**
     * Return гасит open этой сессии и оставляет resolved.
     *
     * @return void
     */
    public function testReturnCancelsOpenAndKeepsResolved(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $otherId = $this->admit($world->getId(), $gameId, 'Ally');
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::MODERATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'character', 'id' => $otherId],
            ],
        ]);
        $session = $this->processes()->open($gameId, null, 'character', $characterId);
        $fight = $this->processes()->open($gameId, $battle['data']['battleId'], 'character', $characterId);
        $kept = $this->processes()->resolve($this->processes()->open($gameId, null, 'character', $characterId)['processId']);
        $other = $this->processes()->open($gameId, null, 'character', $otherId);
        $returned = $this->dispatch('game.returnCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'membershipRevision' => 2,
            'reason' => 'fix',
        ]);
        self::assertTrue($returned['success']);
        self::assertSame('active', $returned['data']['status']);
        self::assertSame('cancelled', $this->processStatus($session['processId']));
        self::assertSame('cancelled', $this->processStatus($fight['processId']));
        self::assertSame('resolved', $this->processStatus($kept['processId']));
        self::assertSame('open', $this->processStatus($other['processId']));
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
    }

    /**
     * Reject и leave open не гасят.
     *
     * @return void
     */
    public function testRejectAndLeaveLeaveProcessOpen(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $submitted = $this->addCharacter($world->getId(), 'Wait');
        $this->membershipFacade()->submit($gameId, $submitted, $this->ownerUserId);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::MODERATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $open = $this->processes()->open($gameId, null, 'character', $characterId);
        self::assertTrue($this->dispatch('game.rejectCharacter', [
            'gameId' => $gameId,
            'characterId' => $submitted,
            'membershipRevision' => 1,
        ])['success']);
        self::assertSame('open', $this->processStatus($open['processId']));
        self::assertTrue($this->dispatch('game.leaveCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'membershipRevision' => 2,
        ])['success']);
        self::assertSame('open', $this->processStatus($open['processId']));
    }

    /**
     * Конец одного боя не гасит сессию и чужой бой.
     *
     * @return void
     */
    public function testEndBattleCancelsOnlyThatBattle(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $first = $this->startBattle($gameId, $characterId, 'b1');
        $second = $this->startBattle($gameId, $characterId, 'b2');
        $doomed = $this->processes()->open($gameId, $first, 'character', $characterId);
        $keptFight = $this->processes()->open($gameId, $second, 'character', $characterId);
        $keptSession = $this->processes()->open($gameId, null, 'character', $characterId);
        $resolved = $this->processes()->resolve($this->processes()->open($gameId, $first, 'character', $characterId)['processId']);
        $ended = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $first,
            'idempotencyKey' => 'e1',
            'expectedVersion' => 1,
        ]);
        self::assertTrue($ended['success']);
        $again = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $first,
            'idempotencyKey' => 'e1',
            'expectedVersion' => 1,
        ]);
        self::assertTrue($again['success']);
        self::assertSame('cancelled', $this->processStatus($doomed['processId']));
        self::assertSame('resolved', $this->processStatus($resolved['processId']));
        self::assertSame('open', $this->processStatus($keptFight['processId']));
        self::assertSame('open', $this->processStatus($keptSession['processId']));
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($card['data']['sessionRunning']);
        self::assertSame('draft', $card['data']['status']);
        self::assertSame(1, $this->countSessions());
    }

    /**
     * Stop гасит оставшиеся open и сессию снимает.
     *
     * @return void
     */
    public function testStopCancelsRemainingAndDropsSession(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $sheet = $this->characterFacade()->get($characterId)->getSheet();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->startBattle($gameId, $characterId, 'b1');
        $session = $this->processes()->open($gameId, null, 'character', $characterId);
        $fight = $this->processes()->open($gameId, $battleId, 'character', $characterId);
        $resolved = $this->processes()->resolve($this->processes()->open($gameId, null, 'character', $characterId)['processId']);
        self::assertTrue($this->dispatch('game.stopSession', ['gameId' => $gameId])['success']);
        self::assertSame('cancelled', $this->processStatus($session['processId']));
        self::assertSame('cancelled', $this->processStatus($fight['processId']));
        self::assertSame('resolved', $this->processStatus($resolved['processId']));
        self::assertCount(3, $this->rows());
        self::assertSame(0, $this->countSessions());
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertFalse($card['data']['sessionRunning']);
        self::assertSame('draft', $card['data']['status']);
        self::assertSame($sheet, $this->characterFacade()->get($characterId)->getSheet());
        $extra = $this->dispatch('game.stopSession', ['gameId' => $gameId, 'targetStatus' => 'completed']);
        self::assertSame('INVALID_PARAMS', $extra['error']['code']);
        $again = $this->dispatch('game.stopSession', ['gameId' => $gameId]);
        self::assertSame('GAME_INVALID', $again['error']['code']);
    }

    /**
     * Повтор resolve не из open.
     *
     * @return void
     */
    public function testResolveClosedIsInvalid(): void
    {
        $world = $this->worldWithRace();
        $gameId = $this->addGame($world->getId());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $open = $this->processes()->open($gameId, null, 'character', $characterId);
        $this->processes()->resolve($open['processId']);
        $this->expectException(GameInvalidException::class);
        $this->processes()->resolve($open['processId']);
    }

    /**
     * Порт process.
     *
     * @return IGameProcesses Фасад.
     */
    private function processes(): IGameProcesses
    {
        $processes = $this->gameApplication()->getLocator()->get(IGameContainer::class)->get(IGameProcesses::class);
        self::assertInstanceOf(IGameProcesses::class, $processes);

        return $processes;
    }

    /**
     * Старт боя.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param string $key Ключ.
     *
     * @return int battleId.
     */
    private function startBattle(int $gameId, int $characterId, string $key): int
    {
        $started = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => $key,
            'participants' => [['type' => 'character', 'id' => $characterId]],
        ]);
        self::assertTrue($started['success']);

        return $started['data']['battleId'];
    }

    /**
     * Статус строки.
     *
     * @param int $processId Process.
     *
     * @return string Статус.
     */
    private function processStatus(int $processId): string
    {
        foreach ($this->rows() as $row) {
            if ($row['id'] === $processId) {
                self::assertIsString($row['status']);

                return $row['status'];
            }
        }

        self::fail('process missing');
    }

    /**
     * Строки process.
     *
     * @return list<array<string, mixed>> Строки.
     */
    private function rows(): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $result = $gateway->open(GameProcessTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));

        return $result->rows();
    }

    /**
     * Число сессий.
     *
     * @return int Число.
     */
    private function countSessions(): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return count($gateway->open(GameSessionTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows());
    }

    /**
     * Мир с расой.
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
     * Черновик.
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
     * Персонаж.
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
}
