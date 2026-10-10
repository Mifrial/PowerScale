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
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameWideStrikeTargetRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTable;
use Mifrial\Roleplay\Game\Table\GameWideStrikeTargetTable;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Широкий удар 1 → N. У принятой цели рейтинг попадания и число урона.
 */
final class GameWideStrikeMysqlTest extends TestCase
{
    use GameMysqlFixture;

    private ?IRequestContext $requestContext = null;

    private ?int $worldRevision = null;

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
     * Две цели, пустые числа, отказ одной не затирает другую.
     *
     * @return void
     */
    public function testTwoTargetsKeepSeparateResults(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $first = $this->createNpc($gameId, 'Guard');
        $second = $this->purchaseCharacteristic($gameId, $this->createNpc($gameId, 'Archer'), 'Archer', 2);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        $one = $this->attack($characterId, [$first['npcId']]);
        $rejected = $this->dispatch('game.declareWideStrike', $this->declareBody($gameId, $battle['data']['battleId'], 'one', 1, $one));
        self::assertSame('INVALID_PARAMS', $rejected['error']['code']);
        $plain = $this->attack($characterId, [$first['npcId'], $second['npcId']]);
        $plain['damage'] = 1;
        $damage = $this->dispatch('game.declareWideStrike', $this->declareBody($gameId, $battle['data']['battleId'], 'dmg', 1, $plain));
        self::assertSame('INVALID_PARAMS', $damage['error']['code']);
        $legacy = [
            'attacker' => ['type' => 'character', 'id' => $characterId],
            'defender' => ['type' => 'npc', 'id' => $first['npcId']],
            'targets' => [
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
            'actionRuleCode' => 'swing',
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
        $stillOne = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'legacy',
            'expectedVersion' => 1,
            'attack' => $legacy,
        ]);
        self::assertSame('INVALID_PARAMS', $stillOne['error']['code']);
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's1',
            1,
            $this->attack($characterId, [$first['npcId'], $second['npcId']]),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame([], $opened['data']['targetResults']);
        self::assertSame(2, $opened['data']['version']);
        self::assertSame(1, $this->countAll(GameWideStrikeTable::class));
        self::assertSame(2, $this->countAll(GameWideStrikeTargetTable::class));
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        $staleBattle = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'stale-battle',
            'expectedVersion' => 1,
            'defense' => ['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertSame('GAME_CONFLICT', $staleBattle['error']['code']);
        self::assertArrayNotHasKey('sheet', $staleBattle['error']['details']);
        self::assertArrayNotHasKey('choices', $staleBattle['error']['details']);
        self::assertArrayNotHasKey('targetResults', $staleBattle['data'] ?? []);
        self::assertSame(1, $this->countAll(GameWideStrikeCommandTable::class));
        $staleSheet = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'stale-sheet',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion'] + 1],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertSame('GAME_CONFLICT', $staleSheet['error']['code']);
        self::assertArrayHasKey('choices', $staleSheet['error']['details']);
        self::assertArrayHasKey('sheet', $staleSheet['error']['details']);
        self::assertArrayNotHasKey('currentSheet', $staleSheet['error']['details']);
        self::assertSame($first['actualVersion'], $staleSheet['error']['details']['currentVersion']);
        self::assertSame('npc', $staleSheet['error']['details']['target']['type']);
        self::assertSame($first['npcId'], $staleSheet['error']['details']['target']['id']);
        self::assertSame(1, $this->countAll(GameWideStrikeCommandTable::class));
        self::assertSame($first['actualVersion'], $this->npcVersion($gameId, $first['npcId']));
        self::assertSame($second['actualVersion'], $this->npcVersion($gameId, $second['npcId']));
        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertNull($closed['data']['targetResults'][0]['code']);
        self::assertNull($closed['data']['targetResults'][1]['code']);
        $replay = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertEquals($closed['data'], $replay['data']);
        $other = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'buckler',
                    'expectedSheetVersion' => $first['actualVersion'],
                ],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertSame('GAME_CONFLICT', $other['error']['code']);
    }

    /**
     * Две concurrent execution одного wide resolve дают один commit и replay.
     *
     * @return void
     */
    public function testConcurrentSameKeyWideResolveReplaysCommittedResult(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for concurrent replay acceptance');
        }

        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Concurrent-Wide-Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createNpc($gameId, 'Concurrent-Wide-Guard');
        $secondNpc = $this->createNpc($gameId, 'Concurrent-Wide-Archer');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concurrent-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['npcId']],
                ['type' => 'npc', 'id' => $secondNpc['npcId']],
            ],
        ]);
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'concurrent-wide-open',
            1,
            $this->attack($characterId, [$npc['npcId'], $secondNpc['npcId']]),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $payload = [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'concurrent-wide-close',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $npc['actualVersion'],
                ],
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $secondNpc['actualVersion'],
                ],
            ]],
        ];
        $commandsBefore = $this->countAll(GameWideStrikeCommandTable::class);

        $results = $this->runConcurrentWideStrikeRequests('game.resolveWideStrike', $payload);
        $this->reconnectGameAfterFork();

        self::assertCount(2, $results);
        self::assertEquals($results[0]['data'], $results[1]['data']);
        self::assertTrue($results[0]['success']);
        self::assertTrue($results[1]['success']);
        self::assertSame($commandsBefore + 1, $this->countAll(GameWideStrikeCommandTable::class));
        self::assertSame(1, $this->countAll(GameWideStrikeTable::class));
        self::assertSame(2, $this->countAll(GameWideStrikeTargetTable::class));
        self::assertSame($npc['actualVersion'], $this->npcVersion($gameId, $npc['npcId']));
        self::assertSame($secondNpc['actualVersion'], $this->npcVersion($gameId, $secondNpc['npcId']));

        $sameBodyReplay = $this->dispatch('game.resolveWideStrike', $payload);
        self::assertTrue($sameBodyReplay['success']);
        self::assertEquals($results[0]['data'], $sameBodyReplay['data']);

        $differentBody = $payload;
        $differentBody['defense']['defenses'][0]['reaction'] = 'dodge';
        $differentBodyResult = $this->dispatch('game.resolveWideStrike', $differentBody);
        self::assertFalse($differentBodyResult['success']);
        self::assertSame('GAME_CONFLICT', $differentBodyResult['error']['code']);
        self::assertSame($commandsBefore + 1, $this->countAll(GameWideStrikeCommandTable::class));
        self::assertSame(1, $this->countAll(GameWideStrikeTable::class));
        self::assertSame(2, $this->countAll(GameWideStrikeTargetTable::class));
        self::assertSame($npc['actualVersion'], $this->npcVersion($gameId, $npc['npcId']));
        self::assertSame($secondNpc['actualVersion'], $this->npcVersion($gameId, $secondNpc['npcId']));
    }

    /**
     * Non-CAS target refusal остаётся per-target и не блокирует valid targets.
     *
     * @return void
     */
    public function testTargetRefusalsRemainPerTarget(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $foreignGameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $localTargets = [
            $this->createNpc($gameId, 'Guard'),
            $this->createNpc($gameId, 'Archer'),
            $this->createNpc($gameId, 'Knight'),
            $this->createNpc($gameId, 'Ranger'),
        ];
        $foreignTarget = $this->createNpc($foreignGameId, 'Foreign');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'refusal-battle',
            'participants' => array_merge(
                [['type' => 'character', 'id' => $characterId]],
                array_map(
                    static fn (array $target): array => ['type' => 'npc', 'id' => $target['npcId']],
                    $localTargets,
                ),
            ),
        ]);
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'refusal-open',
            1,
            $this->attack($characterId, array_column($localTargets, 'npcId')),
        ));
        self::assertTrue($opened['success'], json_encode($opened));

        $this->updateWideTarget($opened['data']['strikeId'], 0, [
            'defender_kind' => 'npc',
            'defender_id' => $foreignTarget['npcId'],
        ]);
        $this->updateWideTarget($opened['data']['strikeId'], 1, [
            'defender_kind' => 'npc',
            'defender_id' => 999999999,
        ]);
        $this->updateWideTarget($opened['data']['strikeId'], 2, [
            'defender_kind' => 'invalid',
        ]);

        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'refusal-close',
            'expectedVersion' => 2,
            'defense' => ['defenses' => array_map(
                static fn (array $target): array => [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $target['actualVersion'],
                ],
                $localTargets,
            )],
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame('GAME_NOT_FOUND', $closed['data']['targetResults'][0]['code']);
        self::assertSame('GAME_NOT_FOUND', $closed['data']['targetResults'][1]['code']);
        self::assertSame('GAME_INVALID', $closed['data']['targetResults'][2]['code']);
        self::assertNull($closed['data']['targetResults'][3]['code']);
        self::assertSame(
            $localTargets[3]['actualVersion'],
            $this->npcVersion($gameId, $localTargets[3]['npcId']),
        );
    }

    /**
     * endBattle сессию не гасит. Stop снимает строки широкого удара.
     *
     * @return void
     */
    public function testEndBattleKeepsSessionAndStopClearsWideStrike(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $first = $this->createNpc($gameId, 'Guard');
        $second = $this->createNpc($gameId, 'Archer');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        self::assertTrue($this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's1',
            1,
            $this->attack($characterId, [$first['npcId'], $second['npcId']]),
        ))['success']);
        $ended = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'e1',
            'expectedVersion' => 2,
        ]);
        self::assertTrue($ended['success'], json_encode($ended));
        self::assertTrue($this->dispatch('game.get', ['id' => $gameId])['data']['sessionRunning']);
        self::assertSame(1, $this->countAll(GameWideStrikeTable::class));
        $stopped = $this->dispatch('game.stopSession', ['gameId' => $gameId, 'targetStatus' => 'completed']);
        self::assertSame('INVALID_PARAMS', $stopped['error']['code']);
        self::assertTrue($this->dispatch('game.stopSession', ['gameId' => $gameId])['success']);
        self::assertSame(0, $this->countAll(GameWideStrikeTable::class));
        self::assertSame(0, $this->countAll(GameWideStrikeTargetTable::class));
        self::assertSame(0, $this->countAll(GameWideStrikeCommandTable::class));
        self::assertSame(0, $this->countAll(GameSessionTable::class));
    }

    /**
     * Повреждение своё у цели: сопротивление и S не общие.
     *
     * @return void
     */
    public function testTargetsKeepOwnInjury(): void
    {
        $world = $this->worldWithWeapon(1, true, 1);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero', [], [[
            'id' => 1,
            'ruleCode' => 'pike',
            'equipped' => true,
        ]]);
        $mailId = $this->admit($world->getId(), $gameId, 'Mail', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'cost' => 2,
                'value' => ['base' => 4, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'mail',
            'equipped' => true,
        ]]);
        $clothId = $this->admit($world->getId(), $gameId, 'Cloth', [], [[
            'id' => 1,
            'ruleCode' => 'cloth',
            'equipped' => true,
        ]]);
        $mailVersion = $this->characterFacade()->get($mailId)->getActualVersion();
        $clothVersion = $this->characterFacade()->get($clothId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-injury',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ],
        ]);
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's-injury',
            1,
            [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'targets' => [
                    ['type' => 'character', 'id' => $mailId],
                    ['type' => 'character', 'id' => $clothId],
                ],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'pike',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-injury',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $mailVersion],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $clothVersion],
            ]],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($this->pair(6), $closed['data']['targetResults'][0]['damage']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['damage']);
        self::assertSame($this->pair(14, -1), $closed['data']['targetResults'][0]['injury']);
        self::assertArrayNotHasKey('S', $closed['data']['targetResults'][1]);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['injury']);
        self::assertSame($mailVersion + 1, $this->characterFacade()->get($mailId)->getActualVersion());
        self::assertSame($clothVersion, $this->characterFacade()->get($clothId)->getActualVersion());
    }

    /**
     * Две цели несут разное сопротивление. Мёртвый код брони не закрывает удар.
     *
     * @return void
     */
    public function testTargetsKeepOwnResistance(): void
    {
        $world = $this->worldWithWeapon(1, true, 1);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero', [], [[
            'id' => 1,
            'ruleCode' => 'blade',
            'equipped' => true,
        ]]);
        $mailId = $this->admit($world->getId(), $gameId, 'Mail', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'cost' => 2,
                'value' => ['base' => 4, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'mail',
            'equipped' => true,
        ]]);
        $clothId = $this->admit($world->getId(), $gameId, 'Cloth', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'cost' => 2,
                'value' => ['base' => 4, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'cloth',
            'equipped' => true,
        ]]);
        $mailVersion = $this->characterFacade()->get($mailId)->getActualVersion();
        $clothVersion = $this->characterFacade()->get($clothId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-resist',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ],
        ]);
        $attack = [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'targets' => [
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'blade',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
        self::assertTrue($this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's-resist',
            1,
            $attack,
        ))['success']);
        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-resist',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $mailVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
            ]],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($this->pair(3), $closed['data']['targetResults'][0]['resistance']);
        self::assertSame($this->pair(1, 1), $closed['data']['targetResults'][1]['resistance']);
        self::assertSame($closed['data']['targetResults'][0]['success'], $closed['data']['targetResults'][1]['success']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][0]['damage']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['damage']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][0]['injury']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['injury']);
    }

    /**
     * Live wide penetration берётся из выбранного inventory instance.
     *
     * @return void
     */
    public function testLiveWidePenetrationUsesSelectedModifierAndReplays(): void
    {
        $world = $this->worldWithWeapon(true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'strength',
                'value' => ['base' => 1, 'size' => 0],
            ]],
        ], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => []],
            ['id' => 2, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => ['penetrator']],
        ]);
        $plateId = $this->admit($world->getId(), $gameId, 'Plate', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'value' => ['base' => 3, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'plate',
            'equipped' => true,
        ]]);
        $clothId = $this->admit($world->getId(), $gameId, 'Leather', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'value' => ['base' => 3, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'leather',
            'equipped' => true,
        ]]);
        $plateVersion = $this->characterFacade()->get($plateId)->getActualVersion();
        $clothVersion = $this->characterFacade()->get($clothId)->getActualVersion();
        $attackerChoices = $this->characterFacade()->get($attackerId)->getChoices();
        self::assertSame('sword', $attackerChoices['inventory'][1]['ruleCode']);
        self::assertSame(2, $attackerChoices['inventory'][1]['id']);
        self::assertSame(['penetrator'], $attackerChoices['inventory'][1]['modifiers']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'penetration-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $plateId],
                ['type' => 'character', 'id' => $clothId],
            ],
        ]);
        $attack = [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'targets' => [
                ['type' => 'character', 'id' => $plateId],
                ['type' => 'character', 'id' => $clothId],
            ],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 2,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'penetration-wide-open',
            1,
            $attack,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'penetration-wide-close',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $plateVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
            ]],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $results = $closed['data']['targetResults'];
        self::assertCount(2, $results);
        self::assertSame(['base' => 6, 'size' => 0], $results[0]['damage']);
        self::assertSame(['base' => 6, 'size' => 0], $results[1]['damage']);
        self::assertSame(['base' => 2, 'size' => 0], $results[0]['resistance']);
        self::assertSame(['base' => 1, 'size' => 0], $results[1]['resistance']);
        self::assertSame(['base' => 5, 'size' => -1], $results[0]['injury']);
        self::assertSame(['base' => 7, 'size' => -1], $results[1]['injury']);
        self::assertSame(['type' => 'character', 'id' => $plateId], $results[0]['target']);
        self::assertSame(['type' => 'character', 'id' => $clothId], $results[1]['target']);
        self::assertSame(
            ['target', 'success', 'damage', 'resistance', 'injury', 'sheetVersion', 'code', 'S'],
            array_keys($results[0]),
        );
        self::assertSame($plateVersion + 1, $this->characterFacade()->get($plateId)->getActualVersion());
        self::assertSame($clothVersion + 1, $this->characterFacade()->get($clothId)->getActualVersion());

        $replay = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'penetration-wide-close',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $plateVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
            ]],
        ]);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertEquals($closed['data'], $replay['data']);
    }

    /**
     * Character и NPC с одинаковыми документами дают одинаковый penetration result.
     *
     * @return void
     */
    public function testWidePenetrationKeepsCharacterNpcDefenderParity(): void
    {
        $world = $this->worldWithWeapon(true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Parity-Attacker', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'strength',
                'value' => ['base' => 1, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'sword',
            'equipped' => true,
            'modifiers' => ['penetrator'],
        ]]);
        $characterId = $this->admit($world->getId(), $gameId, 'Parity-Character', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'value' => ['base' => 3, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'plate',
            'equipped' => true,
        ]]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createNpc($gameId, 'Parity-Npc');
        $npcVersion = $this->setNpcInventory($npc, 'plate');
        $characterVersion = $this->characterFacade()->get($characterId)->getActualVersion();
        self::assertEquals(
            $this->characterFacade()->get($characterId)->getChoices()['inventory'],
            $npcVersion['choices']['inventory'],
        );
        self::assertEqualsCanonicalizing(
            $this->characterFacade()->get($characterId)->getSheet()['characteristicPurchases'],
            $npcVersion['sheet']['characteristicPurchases'],
        );
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'parity-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['npcId']],
            ],
        ]);
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'parity-open',
            1,
            [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'targets' => [
                    ['type' => 'character', 'id' => $characterId],
                    ['type' => 'npc', 'id' => $npc['npcId']],
                ],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'parity-close',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $characterVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $npcVersion['actualVersion']],
            ]],
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($closed['data']['targetResults'][0]['resistance'], $closed['data']['targetResults'][1]['resistance']);
        self::assertSame($closed['data']['targetResults'][0]['injury'], $closed['data']['targetResults'][1]['injury']);
        self::assertSame(['type' => 'character', 'id' => $characterId], $closed['data']['targetResults'][0]['target']);
        self::assertSame(['type' => 'npc', 'id' => $npc['npcId']], $closed['data']['targetResults'][1]['target']);
    }

    /**
     * Поздний CAS второй wide-цели откатывает mutation первой.
     *
     * @return void
     */
    public function testWideLateTargetCasRollsBackFirstPenetrationMutation(): void
    {
        $world = $this->worldWithWeapon(true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'strength',
                'value' => ['base' => 1, 'size' => 0],
            ]],
        ], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => []],
            ['id' => 2, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => ['penetrator']],
        ]);
        $plateId = $this->admit($world->getId(), $gameId, 'Plate', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'value' => ['base' => 3, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'plate',
            'equipped' => true,
        ]]);
        $clothId = $this->admit($world->getId(), $gameId, 'Leather', [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'value' => ['base' => 3, 'size' => 0],
            ]],
        ], [[
            'id' => 1,
            'ruleCode' => 'leather',
            'equipped' => true,
        ]]);
        $plate = $this->characterFacade()->get($plateId);
        $cloth = $this->characterFacade()->get($clothId);
        $attackerVersion = $this->characterFacade()->get($attackerId)->getActualVersion();
        $plateVersion = $plate->getActualVersion();
        $clothVersion = $cloth->getActualVersion();
        $plateSheet = $plate->getSheet();
        $clothSheet = $cloth->getSheet();
        $plateChoices = $plate->getChoices();
        $clothChoices = $cloth->getChoices();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'wide-cas-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $plateId],
                ['type' => 'character', 'id' => $clothId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.declareWideStrike', $this->declareBody(
            $gameId,
            $battleId,
            'wide-cas-open',
            1,
            [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'targets' => [
                    ['type' => 'character', 'id' => $plateId],
                    ['type' => 'character', 'id' => $clothId],
                ],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $strike = $mysql->select('select item_inventory_id, open from game_wide_strike');
        self::assertSame(2, (int) $strike[0]->item_inventory_id);
        $commandsBefore = $this->countAll(GameWideStrikeCommandTable::class);
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, [$clothId], $plateId);
        try {
            $closed = $this->dispatch('game.resolveWideStrike', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'wide-cas-close',
                'expectedVersion' => 2,
                'defense' => ['defenses' => [
                    ['reaction' => 'dodge', 'expectedSheetVersion' => $plateVersion],
                    ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
                ]],
            ]);
        } finally {
            $probe->disarm();
        }

        self::assertSame('GAME_CONFLICT', $closed['error']['code'] ?? null, json_encode($closed));
        self::assertSame(['type' => 'character', 'id' => $clothId], $closed['error']['details']['target'] ?? null);
        $updates = $probe->getUpdates();
        self::assertCount(2, $updates, json_encode($probe->getSeenSql()));
        self::assertSame($plateId, $updates[0]['id']);
        self::assertSame($plateVersion, $updates[0]['expectedVersion']);
        self::assertNotEquals($plateSheet, $updates[0]['sheet']);
        self::assertSame($clothId, $updates[1]['id']);
        self::assertSame($clothVersion, $updates[1]['expectedVersion']);
        $observed = $probe->getObservedMutation();
        self::assertNotNull($observed);
        self::assertSame($plateVersion + 1, $observed['actualVersion']);
        self::assertNotEquals($plateSheet, $observed['sheet']);
        $plateAfter = $this->characterFacade()->get($plateId);
        $clothAfter = $this->characterFacade()->get($clothId);
        self::assertSame($plateVersion, $plateAfter->getActualVersion());
        self::assertEquals($plateSheet, $plateAfter->getSheet());
        self::assertEquals($plateChoices, $plateAfter->getChoices());
        self::assertSame($clothVersion + 1, $clothAfter->getActualVersion());
        self::assertEquals($clothSheet, $clothAfter->getSheet());
        self::assertEquals($clothChoices, $clothAfter->getChoices());
        self::assertSame($attackerVersion, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertSame($commandsBefore, $this->countAll(GameWideStrikeCommandTable::class));
        self::assertSame(1, (int) $mysql->select('select open from game_wide_strike')[0]->open);
        self::assertSame(2, (int) $mysql->select(
            'select state_version from game_battle where id = ?',
            [$battleId],
        )[0]->state_version);
    }

    /**
     * Id каталога plain_roll. Повтор в том же прогоне берёт уже лежащую строку.
     *
     * @param IMechanics $mechanics Каталог.
     *
     * @return int Id.
     */
    private function plainRollId(IMechanics $mechanics): int
    {
        try {
            return $mechanics->add('plain_roll', 'Бросок', '', '1.0.0');
        } catch (MechanicInvalidException) {
            return $mechanics->getByCodeVersion('plain_roll', '1.0.0')->getId();
        }
    }

    /**
     * Id каталога production reliability_cut.
     *
     * @param IMechanics $mechanics Каталог.
     *
     * @return int Id.
     */
    private function reliabilityCutId(IMechanics $mechanics): int
    {
        try {
            return $mechanics->add('reliability_cut', 'Reliability cut', '', '1.0.0');
        } catch (MechanicInvalidException) {
            return $mechanics->getByCodeVersion('reliability_cut', '1.0.0')->getId();
        }
    }

    /**
     * Мир с оружием.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithWeapon(bool|int $penetrationMode = false, mixed ...$legacyArguments): RuleSpaceRecord
    {
        $withPenetration = $penetrationMode === true;
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $reliabilityCutId = $this->reliabilityCutId($mechanics);
        $world = $this->addWorldWithRevision('razrabotka');
        $swordProfile = ['type' => 'strike'];
        if ($withPenetration) {
            $swordProfile = [
                'type' => 'strike',
                'damage' => [
                    'damage_type_code' => 'cut',
                    'formula' => ['type' => 'fixed', 'value' => 6],
                ],
                'penetration' => [
                    'type' => 'actionCharacteristic',
                    'action' => 'swing',
                    'characteristic' => 'strength',
                    'modifier' => [],
                ],
            ];
        }
        $this->worldRevision = $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ], [
                    'characteristic_code' => 'stamina',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ], [
                    'characteristic_code' => 'agility',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [
                        ['cost' => 2, 'value' => ['base' => 4, 'size' => 0]],
                        ['cost' => 4, 'value' => ['base' => 3, 'size' => 0]],
                    ],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing', new RuleVersionBody('language', 'Swing', '', [
                'role' => 'spoken',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('sword', new RuleVersionBody('item', 'Sword', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
                'weapon' => [
                    'weapon_profiles' => [$swordProfile],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('penetrator', new RuleVersionBody('item_modifier', 'Penetrator', '', [
                'type_code' => 'penetrator',
                'operations' => [[
                    'type' => 'action_strength',
                    'field' => 'penetration',
                    'delta' => 2,
                    'profiles' => ['strike'],
                    'damage_type_codes' => [],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('plate', new RuleVersionBody('item', 'Plate', '', [
                'category' => 'armor',
                'armor' => [
                    'defense_slots' => [[
                        'defense' => ['base' => 4, 'size' => 0],
                        'durability' => null,
                        'source_code' => 'plate',
                    ]],
                    'resistance_slots' => [[
                        'damage_type_code' => 'cut',
                        'value' => ['base' => 1, 'size' => 0],
                        'durability' => null,
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('leather', new RuleVersionBody('item', 'Leather', '', [
                'category' => 'armor',
                'armor' => [
                    'resistance_slots' => [[
                        'damage_type_code' => 'cut',
                        'value' => ['base' => 1, 'size' => 0],
                        'durability' => null,
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('pike', new RuleVersionBody('item', 'Pike', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'damage' => [
                            'damage_type_code' => 'cut',
                            'formula' => ['type' => 'fixed', 'value' => 6],
                        ],
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('blade', new RuleVersionBody('item', 'Blade', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'damage' => ['damage_type_code' => 'cut'],
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('cut', new RuleVersionBody('damage_type', 'Cut', '', [
                'forms' => ['genitive' => 'cut', 'dative' => 'cut'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('piercing', new RuleVersionBody('damage_type', 'Piercing', '', [
                'forms' => ['genitive' => 'piercing', 'dative' => 'piercing'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ], [], [[
                'mechanic_id' => $reliabilityCutId,
                'mechanic_payload' => [],
            ]], 'needs_work')),
            RuleCommitEntry::put('cutting', new RuleVersionBody('damage_type', 'Cutting', '', [
                'forms' => ['genitive' => 'cutting', 'dative' => 'cutting'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ], [], [[
                'mechanic_id' => $reliabilityCutId,
                'mechanic_payload' => [],
            ]], 'needs_work')),
            RuleCommitEntry::put('slashing', new RuleVersionBody('damage_type', 'Slashing', '', [
                'forms' => ['genitive' => 'slashing', 'dative' => 'slashing'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ], [], [[
                'mechanic_id' => $reliabilityCutId,
                'mechanic_payload' => [],
            ]], 'needs_work')),
            RuleCommitEntry::put('mail', new RuleVersionBody('item', 'Mail', '', [
                'category' => 'armor',
                'armor' => [
                    'resistance_slots' => [[
                        'damage_type_code' => 'cut',
                        'value' => ['base' => 3, 'size' => 0],
                        'durability' => 1,
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('cloth', new RuleVersionBody('item', 'Cloth', '', [
                'category' => 'armor',
                'armor' => [
                    'resistance_slots' => [[
                        'damage_type_code' => 'cut',
                        'value' => ['base' => 1, 'size' => 1],
                        'durability' => 1,
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('buckler', new RuleVersionBody('item', 'Buckler', '', [
                'category' => 'shield',
                'proficiency_family_code' => 'melee',
                'shield' => [],
                'block_profile' => [
                    'efficiency' => ['base' => 3, 'size' => 0],
                    'defense' => ['base' => 1, 'size' => 0],
                    'resistances' => [],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => [
                    'diceCount' => 1,
                    'dieFaces' => 1,
                ]],
            ]], 'needs_work')),
            RuleCommitEntry::put('agility', new RuleVersionBody('characteristic', 'Agility', '', [
                'dodge_soak' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strength', new RuleVersionBody('characteristic', 'Strength', '', [
                'weapon_mastery' => ['profiles' => ['strike']],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('stamina', new RuleVersionBody('characteristic', 'Stamina', '', [
                'damage_endurance' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('hurt', new RuleVersionBody('state', 'Hurt', '', [
                'value_type' => 'dimensional',
                'damage_remainder' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('tired', new RuleVersionBody('state', 'Tired', '', [
                'value_type' => 'number',
                'damage_exhaustion' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('hit', new RuleVersionBody('check', 'Hit', '', [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => true,
                'concentration_token' => false,
                'willpower' => false,
                'unstable_check' => false,
                'hit_check' => true,
                'initiative' => false,
                'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
            ], [], [], 'needs_work')),
        ])->getRevision();

        return $world;
    }

    /**
     * Фактическая опубликованная ревизия мира текущего теста.
     *
     * @return int Номер ревизии.
     */
    private function worldRevision(): int
    {
        return $this->worldRevision ?? throw new RuntimeException('World revision was not published');
    }

    /**
     * Пишет закупку характеристики на лист NPC. Версия листа растёт.
     *
     * @param int $gameId Игра.
     * @param array{npcId: int, actualVersion: int} $npc Строка.
     * @param string $name Имя.
     * @param int $cost Цена ступени.
     *
     * @return array{npcId: int, actualVersion: int} Строка после записи.
     */
    private function purchaseCharacteristic(int $gameId, array $npc, string $name, int $cost): array
    {
        $current = $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['npcId'],
        ]);
        self::assertTrue($current['success'], json_encode($current));
        $choices = $current['data']['version']['choices'];
        $choices['raceCode'] = 'human';
        $choices['characteristicPurchases'] = [[
            'characteristicCode' => 'agility',
            'cost' => $cost,
        ], [
            'characteristicCode' => 'stamina',
            'cost' => 2,
        ]];
        $preview = $this->dispatch('game.updateNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['npcId'],
            'name' => $name,
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
            'choices' => $choices,
            'sheet' => $current['data']['version']['sheet'],
            'expectedNpcActualVersion' => $npc['actualVersion'],
        ]);
        self::assertSame('conflicts', $preview['data']['kind'] ?? null, json_encode($preview));
        $saved = $this->dispatch('game.updateNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['npcId'],
            'name' => $name,
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
            'choices' => $choices,
            'sheet' => $preview['data']['sheet'],
            'expectedNpcActualVersion' => $npc['actualVersion'],
        ]);
        self::assertTrue($saved['success'], json_encode($saved));

        return [
            'npcId' => $npc['npcId'],
            'actualVersion' => $saved['data']['actualVersion'] ?? $npc['actualVersion'],
        ];
    }

    /**
     * NPC игры.
     *
     * @param int $gameId Игра.
     * @param string $name Имя.
     *
     * @return array{npcId: int, actualVersion: int} Строка.
     */
    private function createNpc(int $gameId, string $name): array
    {
        $npc = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => $name,
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]);

        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $npcId = $npc['data']['npcId'];
        self::assertIsInt($npcId);
        $repo = new GameNpcRepository($gateway);
        $record = $repo->getById($npcId);
        $version = $record->getVersion();
        $sheet = $version['sheet'] ?? null;
        self::assertIsArray($sheet);
        $bought = $sheet['characteristicPurchases'] ?? [];
        self::assertIsArray($bought);
        $bought[] = ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]];
        $bought[] = ['characteristicCode' => 'agility', 'value' => ['base' => 3, 'size' => 0]];
        $bought[] = ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]];
        $sheet['characteristicPurchases'] = $bought;
        if (!array_key_exists('abilityLevels', $sheet)) {
            $sheet['abilityLevels'] = [];
        }
        $version['sheet'] = $sheet;
        $choices = $version['choices'] ?? [];
        if (is_array($choices) && ($choices['inventory'] ?? []) === []) {
            $choices['inventory'] = [['id' => 1, 'ruleCode' => 'buckler', 'equipped' => true]];
            $version['choices'] = $choices;
        }

        return [
            'npcId' => $npcId,
            'actualVersion' => $repo->replaceVersion($npcId, $version, $record->getActualVersion())->getActualVersion(),
        ];
    }

    /**
     * Меняет inventory NPC на выбранный live предмет.
     *
     * @param array{npcId: int, actualVersion: int} $npc NPC.
     * @param string $ruleCode Код предмета.
     *
     * @return array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>} Документ NPC.
     */
    private function setNpcInventory(array $npc, string $ruleCode): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $repo = new GameNpcRepository($gateway);
        $record = $repo->getById($npc['npcId']);
        $version = $record->getVersion();
        $choices = $version['choices'] ?? null;
        $sheet = $version['sheet'] ?? null;
        self::assertIsArray($choices);
        self::assertIsArray($sheet);
        $choices['inventory'] = [[
            'id' => 1,
            'ruleCode' => $ruleCode,
            'equipped' => true,
        ]];
        $version['choices'] = $choices;
        $saved = $repo->replaceVersion($npc['npcId'], $version, $record->getActualVersion());

        return [
            'actualVersion' => $saved->getActualVersion(),
            'choices' => $choices,
            'sheet' => $sheet,
        ];
    }

    /**
     * Пара итога.
     *
     * @param int $base База.
     * @param int $size Размер.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pair(int $base, int $size = 0): array
    {
        return ['base' => $base, 'size' => $size];
    }

    /**
     * Текущая версия NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int Версия.
     */
    private function npcVersion(int $gameId, int $npcId): int
    {
        return $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npcId,
        ])['data']['actualVersion'];
    }

    /**
     * Выбор атаки.
     *
     * @param int $characterId Персонаж.
     * @param list<int> $npcIds Цели.
     *
     * @return array<string, mixed> Тело.
     */
    private function attack(int $characterId, array $npcIds): array
    {
        $targets = [];
        foreach ($npcIds as $npcId) {
            $targets[] = ['type' => 'npc', 'id' => $npcId];
        }

        return [
            'attacker' => ['type' => 'character', 'id' => $characterId],
            'targets' => $targets,
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
    }

    /**
     * Тело объявления.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param string $key Ключ.
     * @param int $version Версия боя.
     * @param array<string, mixed> $attack Выбор.
     *
     * @return array<string, mixed> JSON.
     */
    private function declareBody(int $gameId, int $battleId, string $key, int $version, array $attack): array
    {
        return [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key,
            'expectedVersion' => $version,
            'attack' => $attack,
        ];
    }

    /**
     * Меняет persisted target через public SmartTable boundary.
     *
     * @param int $strikeId Удар.
     * @param int $index Позиция цели.
     * @param array<string, mixed> $values Поля цели.
     *
     * @return void
     */
    private function updateWideTarget(int $strikeId, int $index, array $values): void
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $rows = (new GameWideStrikeTargetRepository($gateway))->getList($strikeId);
        $targetId = $rows[$index]['id'] ?? null;
        self::assertIsInt($targetId);
        $gateway->open(GameWideStrikeTargetTable::class)->records()->update($targetId, $values);
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
     * Допущенный active.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<string, mixed> $sheet Дополнение листа.
     *
     * @return int Персонаж.
     */
    private function admit(int $spaceId, int $gameId, string $name, array $sheet = [], array $inventory = []): int
    {
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => $this->worldRevision(),
            'name' => $name,
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $document = array_merge([
            'abilityLevels' => [],
            'characteristicPurchases' => [],
        ], $sheet);
        $bought = is_array($document['characteristicPurchases']) ? $document['characteristicPurchases'] : [];
        $hasStrength = false;
        foreach ($bought as $purchase) {
            if (is_array($purchase) && ($purchase['characteristicCode'] ?? null) === 'strength') {
                $hasStrength = true;
                break;
            }
        }
        if (!$hasStrength) {
            $bought[] = ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]];
        }
        $bought[] = ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]];
        $document['characteristicPurchases'] = $bought;
        $this->characterFacade()->replaceMigrated($characterId, $name, true, $this->storedChoices($name, $inventory), $document, 2, 1);
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    /**
     * HTTP action.
     *
     * @param string $action Имя.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Ответ.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }

    /**
     * Выполняет два public wide strike request в независимых worker-процессах.
     *
     * @param string $action Action.
     * @param array<string, mixed> $payload JSON payload.
     *
     * @return list<array<string, mixed>> Ответы.
     */
    private function runConcurrentWideStrikeRequests(string $action, array $payload): array
    {
        $workers = [];
        foreach ([0, 1] as $workerIndex) {
            $sockets = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0);
            if ($sockets === false) {
                self::fail('Unable to create wide strike worker socket pair');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork wide strike worker');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                $this->runConcurrentWideStrikeWorker($sockets[1], $action, $payload);
            }

            fclose($sockets[1]);
            $workers[$workerIndex] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        foreach ($workers as $worker) {
            self::assertSame("READY\n", fgets($worker['socket']));
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "GO\n");
        }

        $results = [];
        foreach ($workers as $workerIndex => $worker) {
            $line = fgets($worker['socket']);
            self::assertIsString($line);
            $results[$workerIndex] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            self::assertTrue(pcntl_wifexited($status));
            self::assertSame(0, pcntl_wexitstatus($status));
        }

        return $results;
    }

    /**
     * Загружает приложение worker и выполняет один wide request.
     *
     * @param resource $socket IPC-сокет.
     * @param string $action Action.
     * @param array<string, mixed> $payload JSON payload.
     *
     * @return never
     */
    private function runConcurrentWideStrikeWorker($socket, string $action, array $payload): never
    {
        try {
            $this->bootGameApplication();
            $requestContext = $this->gameApplication()
                ->getLocator()
                ->get(IKernelContainer::class)
                ->get(IRequestContext::class);
            self::assertInstanceOf(IRequestContext::class, $requestContext);
            $this->requestContext = $requestContext;
            $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
            fwrite($socket, "READY\n");
            if (trim((string) fgets($socket)) !== 'GO') {
                throw new RuntimeException('Wide strike worker did not receive start barrier');
            }

            fwrite($socket, json_encode($this->dispatch($action, $payload), JSON_THROW_ON_ERROR) . "\n");
            fclose($socket);
            exit(0);
        } catch (Throwable $exception) {
            @fwrite($socket, json_encode([
                'success' => false,
                'error' => [
                    'code' => 'WORKER_ERROR',
                    'message' => $exception->getMessage(),
                ],
            ], JSON_THROW_ON_ERROR) . "\n");
            fclose($socket);
            exit(1);
        }
    }

    /**
     * Пересобирает приложение после fork.
     *
     * @return void
     */
    private function reconnectGameAfterFork(): void
    {
        $this->bootGameApplication();
        $requestContext = $this->gameApplication()
            ->getLocator()
            ->get(IKernelContainer::class)
            ->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
    }

    /**
     * Актор запроса.
     *
     * @param int $userId Пользователь.
     * @param list<string> $keys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $keys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $keys, false));
    }

    /**
     * Число строк.
     *
     * @param class-string $table Класс.
     *
     * @return int Число.
     */
    private function countAll(string $table): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return count($gateway->open($table)->records()->getList(new ListQuery(
            null,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows());
    }

    /**
     * Выборы листа.
     *
     * @param string $name Имя.
     *
     * @return array<string, mixed> choices.
     */
    private function storedChoices(string $name, array $inventory = []): array
    {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => [],
            'inventory' => $this->inventoryWithIds($inventory === [] ? [
                ['ruleCode' => 'sword', 'equipped' => true],
            ] : $inventory),
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'money' => 0,
            'active' => true,
        ];
    }

    /**
     * Добавляет стабильные id fixture-строкам инвентаря.
     *
     * @param array<int, mixed> $inventory Строки.
     *
     * @return array<int, mixed> Строки с id.
     */
    private function inventoryWithIds(array $inventory): array
    {
        $rows = [];
        foreach (array_values($inventory) as $index => $row) {
            if (is_array($row) && !array_key_exists('id', $row)) {
                $row['id'] = $index + 1;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
