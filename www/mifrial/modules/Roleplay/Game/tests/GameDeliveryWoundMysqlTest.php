<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Service\GameDeliveryPortFactory;
use Mifrial\Roleplay\Game\Service\GameDeliverySync;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameDeliveryWoundMysqlTest extends TestCase
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
     * Рана пишет сигнал. Повтор ключа строку не дублирует. CatchUp и кадр несут battleId.
     *
     * @return void
     */
    public function testWoundSignalCatchUpAndBattleId(): void
    {
        $scene = $this->woundScene();
        $held = $this->dispatch('game.woundAction', $this->holdBody($scene, 'hold-1'));
        self::assertTrue($held['success'], json_encode($held));
        self::assertSame(1, $this->deliveryCount());
        self::assertSame('wound', $this->deliveryRows()[0]['source']);
        self::assertTrue($this->dispatch('game.woundAction', $this->holdBody($scene, 'hold-1'))['success']);
        self::assertSame(1, $this->deliveryCount());
        $this->clearDelivery();
        self::assertSame(0, $this->deliveryCount());
        $frames = $this->frames($this->runSync($scene['gameId'], 0, new FakeGameSyncClock(0)));
        // catchUp восстанавливает и команду боя, и рану.
        self::assertSame(2, $this->deliveryCount());
        $events = $frames[0]['events'] ?? [];
        self::assertIsArray($events);
        $wound = array_values(array_filter(
            $events,
            static fn (mixed $event): bool => is_array($event) && ($event['source'] ?? null) === 'wound',
        ));
        self::assertCount(1, $wound);
        $event = $wound[0];
        self::assertSame($scene['battleId'], $event['battleId'] ?? null);
        self::assertSame([], $event['keys'] ?? null);
    }

    /**
     * @return array{gameId: int, battleId: int, hero: int, stateId: int, sheetVersion: int}
     */
    private function woundScene(): array
    {
        $world = $this->addWorldWithRevision('wound-del-' . substr(uniqid('', true), -8));
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strength', new RuleVersionBody('characteristic', 'Strength', '', [], [], [], 'needs_work')),
            RuleCommitEntry::put('ap', new RuleVersionBody('resource', 'AP', '', [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'turn_refill' => true,
                'limit' => ['base' => 4, 'adjustments' => []],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('squeeze', new RuleVersionBody('ability', 'Squeeze', '', [
                'type' => 'action',
                'action_components' => [['type' => 'resource', 'resource_code' => 'ap', 'amount' => 1]],
                'distinct_weapons' => false,
                'same_weapon' => false,
                'lift_parent_max_weapons' => false,
                'peak_concentration' => false,
                'will_focus' => false,
                'long_tension' => false,
                'multiple' => false,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('nick', new RuleVersionBody('state', 'Nick', '', [
                'value_type' => 'number',
                'aggregation' => 'independent',
            ], [], [], 'needs_work')),
        ]);
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Wound',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $world->getId(),
            'rulesRevision' => 2,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        $hero = $this->admitHero($world->getId(), $gameId);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [['type' => 'character', 'id' => $hero]],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $sheetVersion = $this->putNick($hero);

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'hero' => $hero,
            'stateId' => $this->nickId($hero),
            'sheetVersion' => $sheetVersion,
        ];
    }

    /**
     * @param array{gameId: int, battleId: int, hero: int, stateId: int, sheetVersion: int} $scene Сцена.
     * @param string $key Ключ.
     *
     * @return array<string, mixed> Тело.
     */
    private function holdBody(array $scene, string $key): array
    {
        return [
            'gameId' => $scene['gameId'],
            'idempotencyKey' => $key,
            'operation' => 'hold',
            'actor' => ['kind' => 'character', 'id' => $scene['hero']],
            'target' => ['kind' => 'character', 'id' => $scene['hero']],
            'stateId' => $scene['stateId'],
            'actionRuleCode' => 'squeeze',
            'expectedSheetVersion' => $scene['sheetVersion'],
            'battleId' => $scene['battleId'],
            'expectedBattleVersion' => 1,
        ];
    }

    private function admitHero(int $spaceId, int $gameId): int
    {
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 2,
            'name' => 'Hero',
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $this->characterFacade()->replaceMigrated(
            $characterId,
            'Hero',
            true,
            [
                'name' => 'Hero',
                'raceCode' => 'human',
                'abilities' => [],
                'inventory' => [],
                'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
                'customRules' => [],
                'money' => 0,
                'active' => true,
            ],
            [
                'abilityLevels' => [],
                'resources' => [['ruleCode' => 'ap', 'current' => 4]],
                'characteristicPurchases' => [[
                    'characteristicCode' => 'strength',
                    'value' => ['base' => 4, 'size' => 0],
                ]],
            ],
            2,
            1,
        );
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    private function putNick(int $characterId): int
    {
        $mutations = $this->gameApplication()->getLocator()->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        self::assertInstanceOf(ICharacterActualMutations::class, $mutations);
        $current = $this->characterFacade()->get($characterId)->getActualVersion();

        return $mutations->apply($characterId, $current, [[
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 3,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]])->getActualVersion();
    }

    private function nickId(int $characterId): int
    {
        $states = $this->characterFacade()->get($characterId)->getSheet()['states'] ?? [];
        self::assertIsArray($states);
        self::assertIsArray($states[0] ?? null);
        self::assertIsInt($states[0]['id'] ?? null);

        return $states[0]['id'];
    }

    /**
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }

    /**
     * @param list<string> $keys Ключи.
     */
    private function setActor(int $userId, array $keys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $keys, false));
    }

    private function deliveryCount(): int
    {
        return count($this->deliveryRows());
    }

    /**
     * @return array<int, array<string, mixed>> Строки.
     */
    private function deliveryRows(): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return $gateway->open(GameDeliveryTable::class)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows();
    }

    private function clearDelivery(): void
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $records = $gateway->open(GameDeliveryTable::class)->records();
        foreach ($this->deliveryRows() as $row) {
            if (is_array($row) && is_int($row['id'] ?? null)) {
                $records->delete($row['id']);
            }
        }
    }

    private function runSync(int $gameId, ?int $lastCursor, FakeGameSyncClock $clock): string
    {
        $chunks = '';
        $userAccess = $this->gameApplication()->getLocator()->get(IUserContainer::class)->get(IUserAccess::class);
        self::assertInstanceOf(IUserAccess::class, $userAccess);
        $sync = new GameDeliverySync(
            $userAccess,
            (new GameDeliveryPortFactory())->create($this->gameApplication()->getLocator()),
            $clock,
        );
        $request = $this->createStub(IHttpRequest::class);
        $request->method('getQueryValue')->willReturnCallback(
            static function (string $name) use ($gameId, $lastCursor): mixed {
                if ($name === 'gameId') {
                    return (string) $gameId;
                }

                return $lastCursor === null ? null : (string) $lastCursor;
            },
        );
        $sync->run($request, new SseEmitter(
            static function (string $chunk) use (&$chunks): void {
                $chunks .= $chunk;
            },
            false,
        ));

        return $chunks;
    }

    /**
     * @return array<int, array<string, mixed>> Кадры.
     */
    private function frames(string $chunks): array
    {
        preg_match_all('/event: sync\ndata: ([^\n]+)\n\n/', $chunks, $matches);
        $frames = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $frames[] = $decoded;
            }
        }

        return $frames;
    }
}
