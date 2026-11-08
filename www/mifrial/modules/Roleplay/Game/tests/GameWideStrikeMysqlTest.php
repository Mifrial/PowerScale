<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameActionStrikeRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameActionStrikeTable;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
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
     * Сносит схему Game после последнего класса suite.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        try {
            $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
            $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
            $gateway = $smartTableContainer->get(ISmartTableGateway::class);
            self::assertInstanceOf(ISmartTableGateway::class, $gateway);
            GameMysqlTables::drop($gateway);
        } catch (DatabaseException $exception) {
            // MySQL недоступен — Game-тесты уже пропущены.
        }
    }

    /**
     * Условия широкого удара хранятся и отдаются по каждой цели отдельно.
     *
     * @return void
     */
    public function testWideStrikeKeepsPerTargetConditions(): void
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
            'idempotencyKey' => 'b-wide-conditions',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-wide-conditions',
            'expectedVersion' => 1,
            'action' => [
                'phase' => 'declare',
                'kind' => 'strike',
                'code' => 'swing',
                'actor' => ['type' => 'character', 'id' => $characterId],
                'context' => [
                    'strikes' => [
                        [
                            'itemInventoryId' => 1,
                            'itemRuleCode' => 'sword',
                            'profileType' => 'strike',
                            'profileIndex' => 0,
                            'target' => ['type' => 'npc', 'id' => $first['npcId']],
                            'conditions' => [
                                'distance' => [
                                    'type' => 'ipari',
                                    'value' => ['type' => 'literal', 'value' => ['base' => 0, 'size' => 0]],
                                ],
                                'cover' => ['base' => 0, 'size' => 0],
                                'flank' => true,
                            ],
                        ],
                        [
                            'itemInventoryId' => 1,
                            'itemRuleCode' => 'sword',
                            'profileType' => 'strike',
                            'profileIndex' => 0,
                            'target' => ['type' => 'npc', 'id' => $second['npcId']],
                            'conditions' => [
                                'distance' => [
                                    'type' => 'ipari',
                                    'value' => ['type' => 'literal', 'value' => ['base' => 0, 'size' => 0]],
                                ],
                                'cover' => ['base' => 1, 'size' => -1],
                                'flank' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $view = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
        ]);
        self::assertTrue($view['success'], json_encode($view));
        $targets = $view['data']['openWideStrike']['targets'];
        self::assertTrue($targets[0]['conditions']['flank']);
        self::assertFalse($targets[1]['conditions']['flank']);
        self::assertSame(['base' => 1, 'size' => -1], $targets[1]['conditions']['cover']);

        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $rows = $mysql->select(
            'select target_id, conditions from game_action_strike order by id',
        );
        self::assertCount(2, $rows);
        $byTarget = [];
        foreach ($rows as $row) {
            $byTarget[(int) $row->target_id] = json_decode((string) $row->conditions, true);
        }
        self::assertTrue($byTarget[$first['npcId']]['flank']);
        self::assertFalse($byTarget[$second['npcId']]['flank']);
    }

    /**
     * Метки боя в широком ударе применяются к защите своей цели и тратятся.
     *
     * @return void
     */
    public function testWideBattleMarksApplyPerTarget(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Mark-Attacker');
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Mark-First');
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Mark-Second');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-marks',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $this->seedBattleMarks($battleId, [
            $this->battleMark($firstId, $attackerId),
            $this->battleMark($secondId, $attackerId),
        ]);
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-marks',
            1,
            $this->wideAttack($attackerId, $targets),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-marks',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                [
                    'reaction' => 'dodge',
                    'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion(),
                ],
                [
                    'reaction' => 'dodge',
                    'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion(),
                ],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        foreach ([0, 1] as $index) {
            $advantages = $closed['data']['strikes'][$index]['defenseRoll']['advantages'] ?? [];
            $penalty = array_values(array_filter(
                $advantages,
                static fn (mixed $row): bool => is_array($row)
                    && ($row['source_code'] ?? null) === 'circumstances'
                    && ($row['delta'] ?? null) === -2,
            ));
            self::assertCount(1, $penalty);
        }

        self::assertSame([], $this->battleRow($battleId)['marks'] ?? null);
    }

    /**
     * Жетон концентрации защиты в широком ударе даёт преимущество только своей цели.
     *
     * @return void
     */
    public function testWideDefenseConcentrationAppliesPerTarget(): void
    {
        $world = $this->worldWithWeapon(hitConcentration: true, extraEntries: [
            RuleCommitEntry::put('concentration', new RuleVersionBody('resource', 'Concentration', '', [
                'is_dimensional' => false,
                'auto_add' => false,
                'check_token' => true,
                'limit' => ['base' => 2, 'adjustments' => []],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('peak', new RuleVersionBody('ability', 'Peak', '', [
                'type' => 'skill',
                'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                'grants' => [],
                'peak_concentration' => true,
                'will_focus' => false,
                'long_tension' => false,
            ], [], [], 'needs_work')),
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Concentration-Attacker');
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Concentration-First', [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ]);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Concentration-Second');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-concentration',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-concentration',
            1,
            $this->wideAttack($attackerId, $targets),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-concentration',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                [
                    'reaction' => 'dodge',
                    'spendConcentration' => 1,
                    'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion(),
                ],
                [
                    'reaction' => 'dodge',
                    'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion(),
                ],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $firstAdvantages = $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? [];
        $secondAdvantages = $closed['data']['strikes'][1]['defenseRoll']['advantages'] ?? [];
        $firstConcentration = array_values(array_filter(
            $firstAdvantages,
            static fn (mixed $row): bool => is_array($row) && ($row['source_code'] ?? null) === 'concentration',
        ));
        $secondConcentration = array_values(array_filter(
            $secondAdvantages,
            static fn (mixed $row): bool => is_array($row) && ($row['source_code'] ?? null) === 'concentration',
        ));
        self::assertCount(1, $firstConcentration);
        self::assertSame(1, $firstConcentration[0]['delta'] ?? null);
        self::assertSame([], $secondConcentration);
        $firstResources = $this->characterFacade()->get($firstId)->getSheet()['resources'] ?? [];
        $secondResources = $this->characterFacade()->get($secondId)->getSheet()['resources'] ?? [];
        self::assertSame(1, $this->resourceCurrent($firstResources, 'concentration'));
        self::assertNull($this->resourceCurrent($secondResources, 'concentration'));
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
        $empty = $this->attack($characterId, [$first['npcId']]);
        $empty['targets'] = [];
        $rejected = $this->dispatch('game.runAction', $this->declareBody($gameId, $battle['data']['battleId'], 'empty', 1, $empty));
        self::assertSame('INVALID_PARAMS', $rejected['error']['code']);
        $damageBody = $this->declareBody($gameId, $battle['data']['battleId'], 'dmg', 1, $this->attack($characterId, [$first['npcId'], $second['npcId']]));
        $damageBody['action']['context']['strikes'][0]['damage'] = 1;
        $damage = $this->dispatch('game.runAction', $damageBody);
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
        $stillOne = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'legacy',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($legacy),
        ]);
        self::assertSame('INVALID_PARAMS', $stillOne['error']['code']);
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's1',
            1,
            $this->attack($characterId, [$first['npcId'], $second['npcId']]),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertCount(2, $opened['data']['strikes']);
        self::assertSame(2, $opened['data']['version']);
        self::assertSame(1, $this->countActions('wide_strike'));
        self::assertSame(2, $this->countActionStrikes());
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        $staleBattle = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'stale-battle',
            'expectedVersion' => 1,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ])),
        ]);
        self::assertSame('GAME_CONFLICT', $staleBattle['error']['code']);
        self::assertArrayNotHasKey('sheet', $staleBattle['error']['details']);
        self::assertArrayNotHasKey('choices', $staleBattle['error']['details']);
        self::assertArrayNotHasKey('targetResults', $staleBattle['data'] ?? []);
        self::assertSame(1, $this->countCommands('wide_strike'));
        $staleSheet = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'stale-sheet',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion'] + 1],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ])),
        ]);
        self::assertSame('GAME_CONFLICT', $staleSheet['error']['code']);
        self::assertArrayHasKey('choices', $staleSheet['error']['details']);
        self::assertArrayHasKey('sheet', $staleSheet['error']['details']);
        self::assertArrayNotHasKey('currentSheet', $staleSheet['error']['details']);
        self::assertSame($first['actualVersion'], $staleSheet['error']['details']['currentVersion']);
        self::assertSame('npc', $staleSheet['error']['details']['target']['type']);
        self::assertSame($first['npcId'], $staleSheet['error']['details']['target']['id']);
        self::assertSame(1, $this->countCommands('wide_strike'));
        self::assertSame($first['actualVersion'], $this->npcVersion($gameId, $first['npcId']));
        self::assertSame($second['actualVersion'], $this->npcVersion($gameId, $second['npcId']));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertNull($closed['data']['strikes'][0]['code']);
        self::assertNull($closed['data']['strikes'][1]['code']);
        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ])),
        ]);
        self::assertEquals($closed['data'], $replay['data']);
        $other = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'buckler',
                    'expectedSheetVersion' => $first['actualVersion'],
                ],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $second['actualVersion']],
            ])),
        ]);
        self::assertSame('GAME_CONFLICT', $other['error']['code']);
    }

    /**
     * Потолок РУ типа урона режет повреждение каждой цели широкого удара.
     *
     * @return void
     */
    public function testWideStrikeCapsInjuryByDamageType(): void
    {
        $world = $this->worldWithWeapon(damageTypeCap: 2);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [], [
            ['ruleCode' => 'pike', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createNpc($gameId, 'Guard');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-cap',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['npcId']],
            ],
        ]);
        $attack = $this->attack($characterId, [$npc['npcId']]);
        $attack['itemRuleCode'] = 'pike';
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's-cap',
            1,
            $attack,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-cap',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses(
                [['type' => 'npc', 'id' => $npc['npcId']]],
                [['reaction' => 'ignore', 'expectedSheetVersion' => $npc['actualVersion']]],
            )),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        $strike = $closed['data']['strikes'][0];
        self::assertGreaterThan(2, $strike['success']);
        self::assertSame(2, $strike['srCap']);
        self::assertSame(2, $strike['appliedSr']);
        self::assertSame($this->pair(12), $strike['injury']);

        $calc = $this->attachmentsOfType($this->chatMessages($gameId), 'attack-calc');
        self::assertSame(2, $calc[0]['srCap'] ?? null);
        self::assertSame(2, $calc[0]['appliedSr'] ?? null);
        self::assertSame((string) $strike['success'], $calc[0]['attackSrLabel'] ?? null);
    }

    /**
     * Чат широкого удара: порядок, вложения и ключи доставки.
     *
     * @return void
     */
    public function testWideStrikeChatFormIsStable(): void
    {
        $world = $this->worldWithWeapon(1);
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
            'idempotencyKey' => 'chat-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', $this->declareBody($gameId, $battleId, 'chat-wide-open', 1, [
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
        ]));
        self::assertTrue($opened['success'], json_encode($opened));
        $foreign = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'chat-wide-as-single',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $mailVersion),
        ]);
        self::assertSame('INVALID_PARAMS', $foreign['error']['code']);
        self::assertSame(1, $this->countOpenActions('wide_strike'));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'chat-wide-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $mailVersion],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $clothVersion],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $messages = $this->chatMessages($gameId);
        self::assertGreaterThanOrEqual(5, count($messages));
        self::assertStringContainsString('совершает действие', $messages[0]->getContent());
        self::assertStringContainsString('наносит', $messages[1]->getContent());
        self::assertSame([], $this->contentAttachments($messages[0]));
        self::assertSame([], $this->contentAttachments($messages[1]));
        self::assertSame(['roll', 'roll'], array_column($this->contentAttachments($messages[2]), 'type'));

        $calc = $this->attachmentsOfType($messages, 'attack-calc');
        self::assertCount(2, $calc);
        $details = $this->attachmentsOfType($messages, 'injury-details');
        self::assertCount(2, $details);
        foreach ($details as $detail) {
            self::assertArrayHasKey('injury', $detail);
            self::assertArrayHasKey('damageTypeName', $detail);
            self::assertIsInt($detail['injury']['rating']);
            self::assertSame(max(0, -$detail['injury']['rating']), $detail['injury']['strength']);
        }

        $delivery = $this->deliveryRows();
        self::assertCount(2, $delivery);
        foreach ($delivery as $row) {
            self::assertSame('strike', $row['source']);
            self::assertSame([], $row['keys']);
        }
    }

    /**
     * Обычный открытый удар не даёт объявить широкий в том же бою.
     *
     * @return void
     */
    public function testNormalStrikeBlocksWideStrikeInSameBattle(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Cross-Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createNpc($gameId, 'Cross-Guard');
        $secondNpc = $this->createNpc($gameId, 'Cross-Archer');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'cross-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['npcId']],
                ['type' => 'npc', 'id' => $secondNpc['npcId']],
            ],
        ]);
        $strikeAttack = $this->attack($characterId, [$npc['npcId']]);
        $strikeAttack['actionRuleCode'] = 'swing1';
        $strikeAttack['defender'] = ['type' => 'npc', 'id' => $npc['npcId']];
        unset($strikeAttack['targets']);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'cross-strike',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($strikeAttack),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(1, $this->countActions('strike'));
        $blocked = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'cross-wide',
            2,
            $this->attack($characterId, [$npc['npcId'], $secondNpc['npcId']]),
        ));
        self::assertFalse($blocked['success'], json_encode($blocked));
        self::assertSame('GAME_INVALID', $blocked['error']['code']);
        self::assertSame(0, $this->countActions('wide_strike'));
    }

    /**
     * Владелец цели отвечает за своего персонажа, писатель закрывает NPC.
     *
     * @return void
     */
    public function testTargetOwnerAnswersOwnTargetAndWriterCloses(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $attackerOwner = $this->addUser('wide-owner-attacker');
        $targetOwner = $this->addUser('wide-owner-target');
        $stranger = $this->addUser('wide-owner-stranger');
        $this->addPlayer($gameId, $attackerOwner);
        $this->addPlayer($gameId, $targetOwner);
        $this->addPlayer($gameId, $stranger);
        $attackerId = $this->admit($world->getId(), $gameId, 'Owner-Attacker', [], [], true, $attackerOwner);
        $targetId = $this->admit($world->getId(), $gameId, 'Owner-Target', [], [], true, $targetOwner);
        $targetVersion = $this->characterFacade()->get($targetId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createNpc($gameId, 'Owner-Npc');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'owner-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $targetId],
                ['type' => 'npc', 'id' => $npc['npcId']],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $this->setActor($attackerOwner, []);
        $attack = [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'targets' => [
                ['type' => 'character', 'id' => $targetId],
                ['type' => 'npc', 'id' => $npc['npcId']],
            ],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'owner-wide-open',
            1,
            $attack,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(1, $this->countCommands('wide_strike'));
        $this->setActor($targetOwner, []);
        $targetView = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $battleId,
        ]);
        self::assertTrue($targetView['success'], json_encode($targetView));
        $openView = $targetView['data']['openWideStrike'];
        self::assertSame($opened['data']['strikeId'], $openView['id']);
        self::assertSame(['type' => 'character', 'id' => $attackerId], $openView['attacker']);
        self::assertCount(2, $openView['targets']);
        self::assertFalse($openView['targets'][0]['answered']);
        self::assertFalse($openView['targets'][1]['answered']);
        $this->setActor($targetOwner, []);
        $partial = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-target-answer',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $targetId],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $targetVersion],
            ])),
        ]);
        self::assertTrue($partial['success'], json_encode($partial));
        self::assertFalse($partial['data']['closed']);
        self::assertSame([['index' => 1, 'target' => ['type' => 'npc', 'id' => $npc['npcId']]]], $partial['data']['pendingStrikes']);
        self::assertSame(2, $partial['data']['version']);
        self::assertCount(2, $partial['data']['strikes']);
        self::assertSame('ignore', $partial['data']['strikes'][0]['reaction']);
        self::assertNull($partial['data']['strikes'][1]['reaction']);
        self::assertSame($targetVersion, $this->characterFacade()->get($targetId)->getActualVersion());
        self::assertSame(2, $this->countCommands('wide_strike'));
        $targetView = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $battleId,
        ]);
        self::assertTrue($targetView['success'], json_encode($targetView));
        $openView = $targetView['data']['openWideStrike'];
        self::assertTrue($openView['targets'][0]['answered']);
        self::assertSame('ignore', $openView['targets'][0]['reaction']);
        self::assertFalse($openView['targets'][1]['answered']);
        self::assertArrayNotHasKey('reaction', $openView['targets'][1]);
        $this->setActor($attackerOwner, []);
        $attackerView = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $battleId,
        ]);
        self::assertTrue($attackerView['success'], json_encode($attackerView));
        $attackerTargets = $attackerView['data']['openWideStrike']['targets'];
        self::assertTrue($attackerTargets[0]['answered']);
        self::assertArrayNotHasKey('reaction', $attackerTargets[0]);
        $this->setActor($this->ownerUserId, []);
        $writerView = $this->dispatch('game.getBattleSheets', [
            'gameId' => $gameId,
            'battleId' => $battleId,
        ]);
        self::assertTrue($writerView['success'], json_encode($writerView));
        self::assertSame('ignore', $writerView['data']['openWideStrike']['targets'][0]['reaction']);
        $this->setActor($attackerOwner, []);
        $foreign = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-foreign-answer',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $targetId],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $targetVersion],
            ])),
        ]);
        self::assertSame('AUTH_DENIED', $foreign['error']['code']);
        $this->setActor($stranger, []);
        $strangerAnswer = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-stranger-answer',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $targetId],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $targetVersion],
            ])),
        ]);
        self::assertSame('AUTH_DENIED', $strangerAnswer['error']['code']);
        $this->setActor($targetOwner, []);
        $npcAnswer = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-target-npc',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $npc['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $npc['actualVersion']],
            ])),
        ]);
        self::assertSame('AUTH_DENIED', $npcAnswer['error']['code']);
        self::assertSame(2, $this->countCommands('wide_strike'));
        $this->setActor($targetOwner, []);
        $replaced = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-target-replace',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $targetId],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $targetVersion],
            ])),
        ]);
        self::assertTrue($replaced['success'], json_encode($replaced));
        self::assertFalse($replaced['data']['closed']);
        self::assertSame([['index' => 1, 'target' => ['type' => 'npc', 'id' => $npc['npcId']]]], $replaced['data']['pendingStrikes']);
        self::assertSame(3, $this->countCommands('wide_strike'));
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $answered = $mysql->select(
            'select reaction from game_action_strike where target_id = ? and target_type = ?',
            [$targetId, 'character'],
        );
        self::assertSame('dodge', (string) $answered[0]->reaction);
        $this->setActor($this->ownerUserId, []);
        $writerBody = [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-writer-npc',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $npc['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $npc['actualVersion']],
            ])),
        ];
        $closed = $this->dispatch('game.runAction', $writerBody);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertTrue($closed['data']['closed']);
        self::assertSame([], $closed['data']['pendingStrikes']);
        self::assertCount(2, $closed['data']['strikes']);
        self::assertSame(3, $closed['data']['version']);
        self::assertSame($targetVersion + 1, $this->characterFacade()->get($targetId)->getActualVersion());
        self::assertSame($npc['actualVersion'] + 1, $this->npcVersion($gameId, $npc['npcId']));
        self::assertSame(4, $this->countCommands('wide_strike'));
        $replay = $this->dispatch('game.runAction', $writerBody);
        self::assertEquals($closed['data'], $replay['data']);
        $late = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'owner-late-answer',
            'expectedVersion' => 3,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $targetId],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $targetVersion],
            ])),
        ]);
        self::assertSame('GAME_INVALID', $late['error']['code']);
    }

    /**
     * Объявить широкий удар может владелец атакующего персонажа.
     *
     * @return void
     */
    public function testOwnerDeclaresWideStrikeAndStrangerIsDenied(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $attackerOwner = $this->addUser('declare-wide-attacker');
        $targetOwner = $this->addUser('declare-wide-target');
        $stranger = $this->addUser('declare-wide-stranger');
        $this->addPlayer($gameId, $attackerOwner);
        $this->addPlayer($gameId, $targetOwner);
        $this->addPlayer($gameId, $stranger);
        $attackerId = $this->admit($world->getId(), $gameId, 'Declare-Attacker', [], [], true, $attackerOwner);
        $firstId = $this->admit($world->getId(), $gameId, 'Declare-First', [], [], true, $targetOwner);
        $secondId = $this->admit($world->getId(), $gameId, 'Declare-Second', [], [], true, $targetOwner);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'declare-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $attack = $this->wideAttack($attackerId, [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ]);
        $this->setActor($stranger, []);
        $denied = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'declare-wide-stranger',
            1,
            $attack,
        ));
        self::assertSame('AUTH_DENIED', $denied['error']['code']);
        $this->setActor($targetOwner, []);
        $notOwnAttacker = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'declare-wide-target-owner',
            1,
            $attack,
        ));
        self::assertSame('AUTH_DENIED', $notOwnAttacker['error']['code']);
        self::assertSame(0, $this->countActions('wide_strike'));
        $this->setActor($attackerOwner, []);
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'declare-wide-owner',
            1,
            $attack,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(1, $this->countActions('wide_strike'));
    }

    /**
     * NPC-атакующий объявляет широкий удар только писатель.
     *
     * @return void
     */
    public function testNpcWideAttackerRequiresWriter(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $targetOwner = $this->addUser('npc-wide-target');
        $this->addPlayer($gameId, $targetOwner);
        $firstId = $this->admit($world->getId(), $gameId, 'Npc-Wide-First', [], [], true, $targetOwner);
        $secondId = $this->admit($world->getId(), $gameId, 'Npc-Wide-Second', [], [], true, $targetOwner);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createNpc($gameId, 'Wide-Raider');
        $this->setNpcInventory($npc, 'sword');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'npc-wide-battle',
            'participants' => [
                ['type' => 'npc', 'id' => $npc['npcId']],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $attack = [
            'attacker' => ['type' => 'npc', 'id' => $npc['npcId']],
            'targets' => [
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
        $this->setActor($targetOwner, []);
        $denied = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'npc-wide-player',
            1,
            $attack,
        ));
        self::assertSame('AUTH_DENIED', $denied['error']['code']);
        $this->setActor($this->ownerUserId, []);
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'npc-wide-writer',
            1,
            $attack,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(1, $this->countActions('wide_strike'));
    }

    /**
     * Защита требует явную, уникальную и известную цель удара.
     *
     * @return void
     */
    public function testDefenseRequiresExplicitUniqueAndKnownTargets(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $characterId = $this->admit($world->getId(), $gameId, 'Validation-Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $first = $this->createNpc($gameId, 'Validation-First');
        $second = $this->createNpc($gameId, 'Validation-Second');
        $foreign = $this->createNpc($gameId, 'Validation-Foreign');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'validation-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'validation-wide-open',
            1,
            $this->attack($characterId, [$first['npcId'], $second['npcId']]),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $missing = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'validation-missing-target',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope(['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
            ]]),
        ]);
        self::assertSame('INVALID_PARAMS', $missing['error']['code']);
        $duplicate = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'validation-duplicate-target',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $first['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
            ])),
        ]);
        self::assertSame('INVALID_PARAMS', $duplicate['error']['code']);
        $unknown = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'validation-unknown-target',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $foreign['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $foreign['actualVersion']],
            ])),
        ]);
        self::assertSame('GAME_INVALID', $unknown['error']['code']);
        self::assertSame(1, $this->countCommands('wide_strike'));
        self::assertSame(1, $this->countOpenActions('wide_strike'));
    }

    /**
     * Устаревшая вторая цель откатывает уже сохранённый первый ответ.
     *
     * @return void
     */
    public function testStaleLaterTargetRollsBackEarlierAnswer(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $characterId = $this->admit($world->getId(), $gameId, 'Rollback-Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $first = $this->createNpc($gameId, 'Rollback-First');
        $second = $this->createNpc($gameId, 'Rollback-Second');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'rollback-wide-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            'rollback-wide-open',
            1,
            $this->attack($characterId, [$first['npcId'], $second['npcId']]),
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $conflict = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'rollback-wide-answer',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $second['actualVersion'] + 1],
            ])),
        ]);
        self::assertSame('GAME_CONFLICT', $conflict['error']['code']);
        self::assertSame(1, $this->countCommands('wide_strike'));
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $rows = $mysql->select(
            'select reaction, expected_sheet_version from game_action_strike where target_id in (?, ?) order by id',
            [$first['npcId'], $second['npcId']],
        );
        self::assertCount(2, $rows);
        self::assertNull($rows[0]->reaction);
        self::assertNull($rows[1]->reaction);
        self::assertNull($rows[0]->expected_sheet_version);
        self::assertNull($rows[1]->expected_sheet_version);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'rollback-wide-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $second['actualVersion']],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertTrue($closed['data']['closed']);
    }

    /**
     * Конкурентное объявление оставляет одно открытое действие.
     *
     * @return void
     */
    public function testConcurrentWideDeclarationKeepsSingleOpenAction(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for concurrent declaration acceptance');
        }

        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $characterId = $this->admit($world->getId(), $gameId, 'Concurrent-Declare-Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $first = $this->createNpc($gameId, 'Concurrent-Declare-First');
        $second = $this->createNpc($gameId, 'Concurrent-Declare-Second');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concurrent-declare-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $payload = $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'concurrent-declare',
            1,
            $this->attack($characterId, [$first['npcId'], $second['npcId']]),
        );
        $results = $this->runConcurrentWideStrikeRequests('game.runAction', $payload);
        $this->reconnectGameAfterFork();
        self::assertCount(2, $results);
        $successes = array_filter($results, static fn (array $result): bool => ($result['success'] ?? false) === true);
        self::assertNotEmpty($successes);
        self::assertSame(1, $this->countActions('wide_strike'));
        self::assertSame(2, $this->countActionStrikes());
        self::assertSame(1, $this->countOpenActions('wide_strike'));
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
        $opened = $this->dispatch('game.runAction', $this->declareBody(
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
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $npc['npcId']],
                ['type' => 'npc', 'id' => $secondNpc['npcId']],
            ], [
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $npc['actualVersion'],
                ],
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $secondNpc['actualVersion'],
                ],
            ])),
        ];
        $commandsBefore = $this->countCommands('wide_strike');

        $results = $this->runConcurrentWideStrikeRequests('game.runAction', $payload);
        $this->reconnectGameAfterFork();

        self::assertCount(2, $results);
        self::assertEquals($results[0]['data'], $results[1]['data']);
        self::assertTrue($results[0]['success']);
        self::assertTrue($results[1]['success']);
        self::assertSame($commandsBefore + 1, $this->countCommands('wide_strike'));
        self::assertSame(1, $this->countActions('wide_strike'));
        self::assertSame(2, $this->countActionStrikes());
        self::assertSame($npc['actualVersion'] + 1, $this->npcVersion($gameId, $npc['npcId']));
        self::assertSame($secondNpc['actualVersion'] + 1, $this->npcVersion($gameId, $secondNpc['npcId']));

        $sameBodyReplay = $this->dispatch('game.runAction', $payload);
        self::assertTrue($sameBodyReplay['success']);
        self::assertEquals($results[0]['data'], $sameBodyReplay['data']);

        $differentBody = $payload;
        $differentBody['action']['context']['defenses'][0]['reaction'] = 'dodge';
        $differentBodyResult = $this->dispatch('game.runAction', $differentBody);
        self::assertFalse($differentBodyResult['success']);
        self::assertSame('GAME_CONFLICT', $differentBodyResult['error']['code']);
        self::assertSame($commandsBefore + 1, $this->countCommands('wide_strike'));
        self::assertSame(1, $this->countActions('wide_strike'));
        self::assertSame(2, $this->countActionStrikes());
        self::assertSame($npc['actualVersion'] + 1, $this->npcVersion($gameId, $npc['npcId']));
        self::assertSame($secondNpc['actualVersion'] + 1, $this->npcVersion($gameId, $secondNpc['npcId']));
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
        $opened = $this->dispatch('game.runAction', $this->declareBody(
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

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'refusal-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'npc', 'id' => $foreignTarget['npcId']],
                ['type' => 'npc', 'id' => 999999999],
                ['type' => 'invalid', 'id' => $localTargets[2]['npcId']],
                ['type' => 'npc', 'id' => $localTargets[3]['npcId']],
            ], array_map(
                static fn (array $target): array => [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $target['actualVersion'],
                ],
                $localTargets,
            ))),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame('GAME_NOT_FOUND', $closed['data']['strikes'][0]['code']);
        self::assertSame('GAME_NOT_FOUND', $closed['data']['strikes'][1]['code']);
        self::assertSame('GAME_INVALID', $closed['data']['strikes'][2]['code']);
        self::assertNull($closed['data']['strikes'][3]['code']);
        self::assertSame(
            $localTargets[3]['actualVersion'] + 1,
            $this->npcVersion($gameId, $localTargets[3]['npcId']),
        );
    }

    /**
     * Карточка с потолком принимает одну цель.
     *
     * @return void
     */
    public function testCardCapAllowsSingleTarget(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $target = $this->createNpc($gameId, 'Guard');
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'one-target-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $target['npcId']],
            ],
        ]);

        $declared = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'one-target',
            1,
            $this->attack($characterId, [$target['npcId']]),
        ));

        self::assertTrue($declared['success'], json_encode($declared));
        self::assertIsInt($declared['data']['strikeId']);
        self::assertSame(1, $this->countActions('wide_strike'));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'one-target-close',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses(
                [['type' => 'npc', 'id' => $target['npcId']]],
                [[
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $target['actualVersion'],
                ]],
            )),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertTrue($closed['data']['closed']);
        self::assertCount(1, $closed['data']['strikes']);
        self::assertSame($target['actualVersion'] + 1, $this->npcVersion($gameId, $target['npcId']));
    }

    /**
     * Число целей выше потолка карточки отклоняется.
     *
     * @return void
     */
    public function testTargetCountAboveCardCapIsRejected(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $targets = [];
        for ($index = 0; $index < 5; $index++) {
            $targets[] = $this->createNpc($gameId, 'Guard ' . $index);
        }

        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'cap-battle',
            'participants' => array_merge(
                [['type' => 'character', 'id' => $characterId]],
                array_map(
                    static fn (array $target): array => ['type' => 'npc', 'id' => $target['npcId']],
                    $targets,
                ),
            ),
        ]);
        $rejected = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'cap-over',
            1,
            $this->attack($characterId, array_column($targets, 'npcId')),
        ));

        self::assertSame('GAME_INVALID', $rejected['error']['code']);
        self::assertSame(0, $this->countActions('wide_strike'));
    }

    /**
     * Без max_targets карточка допускает одну цель и не допускает две.
     *
     * @return void
     */
    public function testCardWithoutMaxTargetsAcceptsOnlyOne(): void
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
            'idempotencyKey' => 'no-max-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        $two = $this->attack($characterId, [$first['npcId'], $second['npcId']]);
        $two['actionRuleCode'] = 'swing1';
        $rejected = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'no-max-two',
            1,
            $two,
        ));
        self::assertSame('GAME_INVALID', $rejected['error']['code']);

        $one = $this->attack($characterId, [$first['npcId']]);
        $one['actionRuleCode'] = 'swing1';
        $declared = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'no-max-one',
            1,
            $one,
        ));
        self::assertTrue($declared['success'], json_encode($declared));
    }

    /**
     * attack_mode wide при max_targets 1 — битая карточка.
     *
     * @return void
     */
    public function testInconsistentAttackModeIsRejected(): void
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
            'idempotencyKey' => 'mode-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $first['npcId']],
                ['type' => 'npc', 'id' => $second['npcId']],
            ],
        ]);
        $attack = $this->attack($characterId, [$first['npcId'], $second['npcId']]);
        $attack['actionRuleCode'] = 'widebroken';
        $rejected = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'mode-broken',
            1,
            $attack,
        ));

        self::assertSame('GAME_INVALID', $rejected['error']['code']);
        self::assertSame(0, $this->countActions('wide_strike'));
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
        self::assertTrue($this->dispatch('game.runAction', $this->declareBody(
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
        self::assertSame(1, $this->countActions('wide_strike'));
        $stopped = $this->dispatch('game.stopSession', ['gameId' => $gameId, 'targetStatus' => 'completed']);
        self::assertSame('INVALID_PARAMS', $stopped['error']['code']);
        self::assertTrue($this->dispatch('game.stopSession', ['gameId' => $gameId])['success']);
        self::assertSame(0, $this->countActions('wide_strike'));
        self::assertSame(0, $this->countActionStrikes());
        self::assertSame(0, $this->countCommands('wide_strike'));
        self::assertSame(0, $this->countAll(GameSessionTable::class));
    }

    /**
     * Повреждение своё у цели: сопротивление и S не общие.
     *
     * @return void
     */
    public function testTargetsKeepOwnInjury(): void
    {
        $world = $this->worldWithWeapon(1);
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
        $opened = $this->dispatch('game.runAction', $this->declareBody(
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
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-injury',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $mailVersion],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $clothVersion],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($this->pair(6), $closed['data']['strikes'][0]['damage']);
        self::assertSame($this->pair(6), $closed['data']['strikes'][1]['damage']);
        self::assertSame($this->pair(21, -1), $closed['data']['strikes'][0]['injury']);
        self::assertArrayNotHasKey('S', $closed['data']['strikes'][1]);
        self::assertSame($this->pair(32), $closed['data']['strikes'][1]['injury']);
        self::assertSame($mailVersion + 1, $this->characterFacade()->get($mailId)->getActualVersion());
        self::assertSame($clothVersion + 1, $this->characterFacade()->get($clothId)->getActualVersion());
    }

    /**
     * Широкая атака без карточки у атакующего — отказ.
     *
     * @return void
     */
    public function testWideStrikeRejectsCardNotOwned(): void
    {
        $opened = $this->declareWideCard('wide-locked');

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
    }

    /**
     * Широкая automatic-карточка с невыполненным требованием — отказ.
     *
     * @return void
     */
    public function testWideStrikeRejectsAutomaticCardWithUnmetRequirement(): void
    {
        $opened = $this->declareWideCard('wide-gated');

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
    }

    /**
     * Широкая automatic-карточка без уровня объявляется.
     *
     * @return void
     */
    public function testWideStrikeAcceptsAutomaticCardWithoutLevel(): void
    {
        $opened = $this->declareWideCard('swing');

        self::assertTrue($opened['success'], json_encode($opened));
    }

    /**
     * Объявляет широкую атаку по одной цели заданной карточкой.
     *
     * @param string $actionRuleCode Карточка.
     *
     * @return array<string, mixed> Ответ объявления.
     */
    private function declareWideCard(string $actionRuleCode): array
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero-' . $actionRuleCode);
        $targetId = $this->admit($world->getId(), $gameId, 'Target-' . $actionRuleCode);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-card-' . $actionRuleCode,
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $targetId],
            ],
        ]);
        $attack = $this->attack($attackerId, []);
        $attack['targets'] = [['type' => 'character', 'id' => $targetId]];
        $attack['actionRuleCode'] = $actionRuleCode;

        return $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's-wide-card-' . $actionRuleCode,
            1,
            $attack,
        ));
    }

    /**
     * Широкая атака не применяет бонус к характеристике урона от РУ.
     *
     * @return void
     */
    public function testWideStrikeIgnoresSuccessRatingDamageBonus(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero', [], [[
            'id' => 1,
            'ruleCode' => 'pike',
            'equipped' => true,
        ]]);
        $firstId = $this->admit($world->getId(), $gameId, 'First');
        $secondId = $this->admit($world->getId(), $gameId, 'Second');
        $firstVersion = $this->characterFacade()->get($firstId)->getActualVersion();
        $secondVersion = $this->characterFacade()->get($secondId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-rating-bonus',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's-rating-bonus',
            1,
            [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'targets' => [
                    ['type' => 'character', 'id' => $firstId],
                    ['type' => 'character', 'id' => $secondId],
                ],
                'actionRuleCode' => 'swingfx',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'pike',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-rating-bonus',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ], [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $firstVersion],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $secondVersion],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(8, $closed['data']['strikes'][0]['success']);
        self::assertSame($this->pair(6), $closed['data']['strikes'][0]['damage']);
        self::assertSame($this->pair(6), $closed['data']['strikes'][1]['damage']);
    }

    /**
     * Две цели несут разное сопротивление. Мёртвый код брони не закрывает удар.
     *
     * @return void
     */
    public function testTargetsKeepOwnResistance(): void
    {
        $world = $this->worldWithWeapon(1);
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
        self::assertTrue($this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            's-resist',
            1,
            $attack,
        ))['success']);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-resist',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $mailId],
                ['type' => 'character', 'id' => $clothId],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $mailVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($this->pair(3), $closed['data']['strikes'][0]['resistance']);
        self::assertSame($this->pair(1, 1), $closed['data']['strikes'][1]['resistance']);
        self::assertSame($closed['data']['strikes'][0]['success'], $closed['data']['strikes'][1]['success']);
        self::assertSame($this->pair(0), $closed['data']['strikes'][0]['damage']);
        self::assertSame($this->pair(0), $closed['data']['strikes'][1]['damage']);
        self::assertSame($this->pair(0), $closed['data']['strikes'][0]['injury']);
        self::assertSame($this->pair(0), $closed['data']['strikes'][1]['injury']);
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
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [], [
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
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battle['data']['battleId'],
            'penetration-wide-open',
            1,
            $attack,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'penetration-wide-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $plateId],
                ['type' => 'character', 'id' => $clothId],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $plateVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $results = $closed['data']['strikes'];
        self::assertCount(2, $results);
        self::assertSame(['base' => 6, 'size' => 0], $results[0]['damage']);
        self::assertSame(['base' => 6, 'size' => 0], $results[1]['damage']);
        self::assertSame(['base' => 1, 'size' => 0], $results[0]['resistance']);
        self::assertSame(['base' => 1, 'size' => 0], $results[1]['resistance']);
        self::assertSame(['base' => 37, 'size' => -1], $results[0]['injury']);
        self::assertSame(['base' => 37, 'size' => -1], $results[1]['injury']);
        self::assertSame(['type' => 'character', 'id' => $plateId], $results[0]['target']);
        self::assertSame(['type' => 'character', 'id' => $clothId], $results[1]['target']);
        self::assertSame(
            [
                'index',
                'itemInventoryId',
                'itemRuleCode',
                'profileType',
                'profileIndex',
                'instanceIndex',
                'target',
                'reaction',
                'conditions',
                'success',
                'damage',
                'resistance',
                'injury',
                'sheetVersion',
                'code',
                'defenseRoll',
                'S',
                'appliedSr',
                'srCap',
                'wound',
                'exhaustionDelta',
                'damageRemainder',
                'endurance',
                'will',
                'willOutcome',
                'maim',
                'chat',
            ],
            array_keys($results[0]),
        );
        self::assertSame($plateVersion + 1, $this->characterFacade()->get($plateId)->getActualVersion());
        self::assertSame($clothVersion + 1, $this->characterFacade()->get($clothId)->getActualVersion());

        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'penetration-wide-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $plateId],
                ['type' => 'character', 'id' => $clothId],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $plateVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
            ])),
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
        $attackerId = $this->admit($world->getId(), $gameId, 'Parity-Attacker', [], [[
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
        ]], false);
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
        $opened = $this->dispatch('game.runAction', $this->declareBody(
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
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'parity-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['npcId']],
            ], [
                ['reaction' => 'dodge', 'expectedSheetVersion' => $characterVersion],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $npcVersion['actualVersion']],
            ])),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($closed['data']['strikes'][0]['resistance'], $closed['data']['strikes'][1]['resistance']);
        self::assertSame($closed['data']['strikes'][0]['injury'], $closed['data']['strikes'][1]['injury']);
        self::assertSame(['type' => 'character', 'id' => $characterId], $closed['data']['strikes'][0]['target']);
        self::assertSame(['type' => 'npc', 'id' => $npc['npcId']], $closed['data']['strikes'][1]['target']);
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
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [], [
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
        $opened = $this->dispatch('game.runAction', $this->declareBody(
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
        $strike = $mysql->select("select s.item_inventory_id, a.open from game_action_strike s join game_action a on a.id = s.action_id where a.kind = 'wide_strike'");
        self::assertSame(2, (int) $strike[0]->item_inventory_id);
        $commandsBefore = $this->countCommands('wide_strike');
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, [$clothId], $plateId);
        try {
            $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'wide-cas-close',
            'expectedVersion' => 2,
            'action' => $this->wideDefenseEnvelope($this->defenses([
                    ['type' => 'character', 'id' => $plateId],
                    ['type' => 'character', 'id' => $clothId],
                ], [
                    ['reaction' => 'dodge', 'expectedSheetVersion' => $plateVersion],
                    ['reaction' => 'dodge', 'expectedSheetVersion' => $clothVersion],
                ])),
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
        self::assertSame($commandsBefore, $this->countCommands('wide_strike'));
        self::assertSame(1, (int) $mysql->select("select open from game_action where kind = 'wide_strike'")[0]->open);
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
     * Прикрытие широкого удара переносит цель по строке.
     *
     * @return void
     */
    public function testWideCoverReactionMovesTargetPerRow(): void
    {
        $world = $this->worldWithWeapon(extraEntries: $this->reactionEntries());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $points = ['resources' => [['ruleCode' => 'action-points', 'current' => 10]]];
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Cover-Attacker', $points);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Cover-First', $points);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Cover-Second', $points);
        $covererId = $this->admit($world->getId(), $gameId, 'Wide-Cover-Coverer', $points, [
            ['ruleCode' => 'buckler', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-cover',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
                ['type' => 'character', 'id' => $covererId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-cover',
            1,
            $this->wideAttack($attackerId, $targets),
        ));
        self::assertTrue($opened['success'], json_encode($opened));

        $cover = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'wide-cover-declare',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'prikrytie',
                'actor' => ['type' => 'character', 'id' => $covererId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($covererId)->getActualVersion(),
                    'target' => $targets[0],
                    'cover' => [
                        'reaction' => 'block',
                        'blockItemInventoryId' => 1,
                        'blockItemProfileIndex' => 0,
                        'blockItemRuleCode' => 'buckler',
                    ],
                ],
            ],
        ]);
        self::assertTrue($cover['success'], json_encode($cover));
        self::assertSame(0, $cover['data']['spent'] ?? null);
        self::assertSame(10, $this->actionPointsOf($covererId));
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $openRow = (new GameActionRepository($gateway))->findOpenWithStrikes($battleId, 'wide_strike');
        self::assertCount(1, $openRow['strikes'][0]['coverers'] ?? []);
        self::assertSame([], $openRow['strikes'][1]['coverers'] ?? []);

        $messages = $this->chatMessages($gameId);
        $coverMessages = array_values(array_filter(
            $messages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'прикрывает'),
        ));
        self::assertCount(1, $coverMessages);
        $thread = $this->threadOf($coverMessages[0]);
        self::assertSame('attack-wide-' . $opened['data']['strikeId'], $thread['id'] ?? null);

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-cover',
            'expectedVersion' => $cover['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion(),
                ],
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion(),
                ],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(8, $this->actionPointsOf($covererId));

        if (($closed['data']['closed'] ?? true) === false) {
            $awaiting = $closed['data']['awaiting'][0] ?? null;
            self::assertSame('actual_target', $awaiting['kind'] ?? null);
            self::assertSame('character:' . $firstId, $awaiting['rowKey'] ?? null);
            $keys = array_map(
                static fn (array $option): string => (string) ($option['key'] ?? ''),
                is_array($awaiting['options'] ?? null) ? $awaiting['options'] : [],
            );
            self::assertContains('character:' . $firstId, $keys);
            self::assertContains('character:' . $covererId, $keys);

            $resolved = $this->dispatch('game.resolveStrikeWindow', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'w-wide-cover',
                'expectedVersion' => $closed['data']['version'],
                'window' => [
                    'row' => 'character:' . $firstId,
                    'actualTarget' => 'character:' . $covererId,
                ],
            ]);
            self::assertTrue($resolved['success'], json_encode($resolved));
            self::assertTrue($resolved['data']['closed'] ?? false, json_encode($resolved));
            $closed = $resolved;
        }

        $rowsResult = $closed['data']['strikes'] ?? $closed['data']['targetResults'] ?? [];
        $targetIds = array_map(
            static fn (array $row): int => (int) ($row['target']['id'] ?? 0),
            is_array($rowsResult) ? $rowsResult : [],
        );
        sort($targetIds);
        $expectedIds = [$covererId, $secondId];
        sort($expectedIds);
        self::assertSame($expectedIds, $targetIds);
    }

    /**
     * Провал прикрытия по строке ждёт выбора фактической цели.
     *
     * @return void
     */
    public function testWideCoverAllFailedPausesPerRow(): void
    {
        $entries = $this->reactionEntries();
        $entries[] = RuleCommitEntry::put('cursed-shield', new RuleVersionBody('item', 'Cursed shield', '', [
            'category' => 'shield',
            'proficiency_family_code' => 'shield',
            'block_profile' => [
                'efficiency' => ['base' => -100, 'size' => 0],
                'defense' => ['base' => -100, 'size' => 0],
                'resistances' => [],
            ],
        ], [], [], 'needs_work'));
        $world = $this->worldWithWeapon(extraEntries: $entries);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $points = ['resources' => [['ruleCode' => 'action-points', 'current' => 10]]];
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Fail-Attacker', $points);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Fail-First', $points, [
            ['ruleCode' => 'cursed-shield', 'equipped' => true],
        ]);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Fail-Second', $points);
        $covererId = $this->admit($world->getId(), $gameId, 'Wide-Fail-Coverer', $points, [
            ['ruleCode' => 'cursed-shield', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-fail',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
                ['type' => 'character', 'id' => $covererId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-fail',
            1,
            $this->wideAttack($attackerId, $targets),
        ));
        self::assertTrue($opened['success'], json_encode($opened));

        $cover = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'wide-fail-declare',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'prikrytie',
                'actor' => ['type' => 'character', 'id' => $covererId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($covererId)->getActualVersion(),
                    'target' => $targets[0],
                    'cover' => [
                        'reaction' => 'block',
                        'blockItemInventoryId' => 1,
                        'blockItemProfileIndex' => 0,
                        'blockItemRuleCode' => 'cursed-shield',
                    ],
                ],
            ],
        ]);
        self::assertTrue($cover['success'], json_encode($cover));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-fail',
            'expectedVersion' => $cover['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'cursed-shield',
                    'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion(),
                ],
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion(),
                ],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertFalse($closed['data']['closed'] ?? true, json_encode($closed));
        $awaiting = $closed['data']['awaiting'][0] ?? null;
        self::assertSame('actual_target', $awaiting['kind'] ?? null);
        self::assertSame('character', $awaiting['actor']['type'] ?? null);
        self::assertSame($attackerId, $awaiting['actor']['id'] ?? null);
        self::assertSame('character:' . $firstId, $awaiting['rowKey'] ?? null);
        $keys = array_map(
            static fn (array $option): string => (string) ($option['key'] ?? ''),
            is_array($awaiting['options'] ?? null) ? $awaiting['options'] : [],
        );
        self::assertContains('character:' . $firstId, $keys);
        self::assertContains('character:' . $covererId, $keys);
        self::assertSame(8, $this->actionPointsOf($covererId));
        self::assertSame(8, $this->actionPointsOf($firstId));

        $repeat = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-fail-repeat',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'cursed-shield',
                    'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion(),
                ],
                [
                    'reaction' => 'ignore',
                    'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion(),
                ],
            ])),
        ]);
        self::assertTrue($repeat['success'], json_encode($repeat));
        self::assertFalse($repeat['data']['closed'] ?? true, json_encode($repeat));
        self::assertSame(8, $this->actionPointsOf($covererId));
        self::assertSame(8, $this->actionPointsOf($firstId));

        $resolved = $this->dispatch('game.resolveStrikeWindow', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'w-wide-fail',
            'expectedVersion' => $closed['data']['version'],
            'window' => [
                'row' => 'character:' . $firstId,
                'actualTarget' => 'character:' . $firstId,
            ],
        ]);
        self::assertTrue($resolved['success'], json_encode($resolved));
        self::assertTrue($resolved['data']['closed'] ?? false, json_encode($resolved));
        $rowsResult = $resolved['data']['strikes'] ?? $resolved['data']['targetResults'] ?? [];
        $targetIds = array_map(
            static fn (array $row): int => (int) ($row['target']['id'] ?? 0),
            is_array($rowsResult) ? $rowsResult : [],
        );
        sort($targetIds);
        $expectedIds = [$firstId, $secondId];
        sort($expectedIds);
        self::assertSame($expectedIds, $targetIds);

        $contents = array_map(
            static fn (MessageRecord $message): string => $message->getContent(),
            $this->chatMessages($gameId),
        );
        self::assertNotSame([], array_filter(
            $contents,
            static fn (string $content): bool => str_contains($content, 'прикрытие не удаётся'),
        ));
        $blockActors = [];
        foreach ($this->chatMessages($gameId) as $message) {
            foreach ($message->getAttachments() as $attachment) {
                $payload = is_array($attachment) ? ($attachment['payload'] ?? null) : null;
                if (($attachment['type'] ?? null) !== 'roll' || !is_array($payload)) {
                    continue;
                }

                if (str_contains((string) ($payload['label'] ?? ''), 'Блок')) {
                    $blockActors[] = $payload['actorKey'] ?? null;
                }
            }
        }

        self::assertContains('character:' . $firstId, $blockActors);
        self::assertContains('character:' . $covererId, $blockActors);
    }

    /**
     * Поддержка широкого удара усиливает бросок выбранной цели.
     *
     * @return void
     */
    public function testWideSupportAddsAdvantageToTargetRow(): void
    {
        $world = $this->worldWithWeapon(extraEntries: $this->reactionEntries());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $points = ['resources' => [['ruleCode' => 'action-points', 'current' => 10]]];
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Support-Attacker', $points);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Support-First', $points);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Support-Second', $points);
        $supporterId = $this->admit($world->getId(), $gameId, 'Wide-Support-Supporter', $points);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-support',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
                ['type' => 'character', 'id' => $supporterId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-support',
            1,
            $this->wideAttack($attackerId, $targets),
        ));
        self::assertTrue($opened['success'], json_encode($opened));

        $support = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'wide-support-declare',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'support-any',
                'actor' => ['type' => 'character', 'id' => $supporterId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($supporterId)->getActualVersion(),
                    'target' => $targets[0],
                    'support' => [
                        'side' => 'defender',
                        'itemInventoryId' => 1,
                        'itemRuleCode' => 'sword',
                        'profileType' => 'strike',
                    ],
                ],
            ],
        ]);
        self::assertTrue($support['success'], json_encode($support));
        self::assertSame(10, $this->actionPointsOf($supporterId));

        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $openRow = (new GameActionRepository($gateway))->findOpenWithStrikes($battleId, 'wide_strike');
        self::assertCount(1, $openRow['strikes'][0]['supporters'] ?? []);
        self::assertSame([], $openRow['strikes'][1]['supporters'] ?? []);

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-support',
            'expectedVersion' => $support['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                [
                    'reaction' => 'dodge',
                    'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion(),
                ],
                [
                    'reaction' => 'dodge',
                    'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion(),
                ],
            ])),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(8, $this->actionPointsOf($supporterId));

        $firstAdvantages = $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? [];
        $secondAdvantages = $closed['data']['strikes'][1]['defenseRoll']['advantages'] ?? [];
        $matched = array_values(array_filter(
            $firstAdvantages,
            static fn (mixed $row): bool => is_array($row)
                && ($row['source_code'] ?? null) === 'circumstances'
                && ($row['delta'] ?? null) === 1,
        ));
        self::assertNotEmpty($matched, json_encode($firstAdvantages));
        $unexpected = array_values(array_filter(
            $secondAdvantages,
            static fn (mixed $row): bool => is_array($row)
                && ($row['source_code'] ?? null) === 'circumstances'
                && ($row['delta'] ?? null) === 1,
        ));
        self::assertSame([], $unexpected);
    }

    /**
     * Широкий удар исполняет модификатор один раз после всех целей.
     *
     * @return void
     */
    public function testWideStrikeRunsModifierOnceAfterAllTargets(): void
    {
        $world = $this->worldWithWeapon(
            withPendingEffects: true,
            extraEntries: $this->modifierEntries(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Rush', abilities: [
            ['ruleCode' => 'rush', 'level' => 1],
        ]);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Rush-First', ['hp' => 100]);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Rush-Second', ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-rush',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-rush',
            1,
            $this->wideAttack($attackerId, $targets) + ['modifiers' => ['rush']],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $beforeClose = $this->characterFacade()->get($attackerId)->getActualVersion();
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-rush',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion()],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion()],
            ])),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(['rush'], $closed['data']['modifiers']['codes'] ?? null);
        self::assertTrue($closed['data']['modifiers']['success'] ?? null, json_encode($closed));
        self::assertTrue($closed['data']['modifiers']['skipped'] ?? null, json_encode($closed));
        self::assertCount(1, $closed['data']['modifiers']['injuries'] ?? []);
        self::assertSame($beforeClose + 1, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertNotContains(
            'after_action_until_resource_spent_check_modifier',
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
    }

    /**
     * Режим each наносит урон по себе за каждую покрытую строку.
     *
     * @return void
     */
    public function testWideStrikeEachModeDamagesEveryTargetRow(): void
    {
        $world = $this->worldWithWeapon(extraEntries: $this->modifierEntries(mode: 'each'));
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Rush-Each', abilities: [
            ['ruleCode' => 'rush', 'level' => 1],
        ]);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Each-First', ['hp' => 100]);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Each-Second', ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-each',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-each',
            1,
            $this->wideAttack($attackerId, $targets) + ['modifiers' => ['rush']],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $beforeClose = $this->characterFacade()->get($attackerId)->getActualVersion();
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-each',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion()],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion()],
            ])),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertCount(2, $closed['data']['modifiers']['injuries'] ?? []);
        self::assertSame($beforeClose + 2, $this->characterFacade()->get($attackerId)->getActualVersion());
    }

    /**
     * Модификатор вне области строк отвергается на объявлении.
     *
     * @return void
     */
    public function testWideStrikeRejectsModifierOutsideScope(): void
    {
        $world = $this->worldWithWeapon(extraEntries: $this->modifierEntries(scope: ['shoot']));
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Scope', abilities: [
            ['ruleCode' => 'rush', 'level' => 1],
        ]);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Scope-First');
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Scope-Second');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-scope',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-scope',
            1,
            $this->wideAttack($attackerId, [
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ]) + ['modifiers' => ['rush']],
        ));

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        self::assertNull((new GameActionRepository($gateway))->findOpenByBattle($battleId, 'wide_strike'));
    }

    /**
     * Провал проверки не пишет лист и оставляет последействие действия.
     *
     * @return void
     */
    public function testWideStrikeModifierFailureKeepsAfterAction(): void
    {
        $world = $this->worldWithWeapon(
            withPendingEffects: true,
            extraEntries: $this->modifierEntries(difficulty: 99),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Wide-Rush-Fail', [
            'characteristicPurchases' => [['characteristicCode' => 'will', 'value' => ['base' => 3, 'size' => 0]]],
        ], abilities: [
            ['ruleCode' => 'rush', 'level' => 1],
        ]);
        $firstId = $this->admit($world->getId(), $gameId, 'Wide-Fail-First', ['hp' => 100]);
        $secondId = $this->admit($world->getId(), $gameId, 'Wide-Fail-Second', ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-wide-fail',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $targets = [
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ];
        $opened = $this->dispatch('game.runAction', $this->declareBody(
            $gameId,
            $battleId,
            's-wide-fail',
            1,
            $this->wideAttack($attackerId, $targets) + ['modifiers' => ['rush']],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $beforeClose = $this->characterFacade()->get($attackerId)->getActualVersion();
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wide-fail',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->wideDefenseEnvelope($this->defenses($targets, [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $this->characterFacade()->get($firstId)->getActualVersion()],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $this->characterFacade()->get($secondId)->getActualVersion()],
            ])),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertFalse($closed['data']['modifiers']['success'] ?? null);
        self::assertFalse($closed['data']['modifiers']['skipped'] ?? null);
        self::assertSame([], $closed['data']['modifiers']['injuries'] ?? null);
        self::assertSame($beforeClose, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertContains(
            'after_action_until_resource_spent_check_modifier',
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
    }

    /**
     * Записи карточки-модификатора «Яростного рывка».
     *
     * @param string $mode Режим урона по себе.
     * @param list<string> $scope Профили области.
     * @param int|null $difficulty Сложность проверки или null.
     *
     * @return list<RuleCommitEntry> Записи.
     */
    private function modifierEntries(
        string $mode = 'strongest',
        array $scope = ['strike'],
        ?int $difficulty = null,
    ): array {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $entries = [
            RuleCommitEntry::put('rush', new RuleVersionBody('ability', 'Rush', '', [
                'type' => 'skill',
                'zones' => ['or' => ['kind' => 'automatic']],
                'parent_ability_code' => 'swing',
                'action_effects' => [[
                    'type' => 'action_modifier',
                    'trigger' => 'after_strike',
                    'scope' => ['components' => $scope, 'hit_count' => 1],
                    ...($difficulty === null ? [] : ['check' => ['code' => 'willpower-check', 'difficulty' => $difficulty]]),
                    'effects' => [
                        [
                            'type' => 'self_damage',
                            'size_delta' => -1,
                            'damage_type_code' => 'cut',
                            'ignore_defense' => true,
                            'mode' => $mode,
                        ],
                        ['type' => 'skip_after_action'],
                    ],
                ]],
            ], [], [], 'needs_work')),
        ];
        if ($difficulty === null) {
            return $entries;
        }

        $entries[] = RuleCommitEntry::put('willpower-check', new RuleVersionBody('check', 'Willpower', '', [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => false,
            'concentration_token' => false,
            'willpower' => true,
            'unstable_check' => false,
            'hit_check' => false,
            'initiative' => false,
            'characteristic_code' => 'will',
            'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
        ], [], [[
            'mechanic_id' => $rollId,
            'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
        ]], 'needs_work'));

        return $entries;
    }

    /**
     * Типы ожиданий атакующего в строке боя.
     *
     * @param int $battleId Бой.
     * @param string $key Ключ участника.
     *
     * @return list<string> Типы.
     */
    private function pendingTypes(int $battleId, string $key): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = $gateway->open(GameBattleTable::class)->records()->getById($battleId);
        self::assertIsArray($row);
        $pending = $row['pending_action_effects'] ?? [];
        self::assertIsArray($pending);
        $entries = $pending[$key] ?? [];
        self::assertIsArray($entries);
        $types = [];
        foreach ($entries as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            $type = is_array($effect) ? ($effect['type'] ?? null) : null;
            self::assertIsString($type);
            $types[] = $type;
        }

        return $types;
    }

    /**
     * Правила реакций и блока для широкого удара.
     *
     * @return list<RuleCommitEntry> Записи.
     */
    private function reactionEntries(): array
    {
        return [
            RuleCommitEntry::put('action-points', new RuleVersionBody('resource', 'Action points', '', [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'turn_refill' => true,
                'limit' => ['base' => 10, 'adjustments' => []],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('prikrytie', new RuleVersionBody('ability', 'Cover', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                ]],
                'reaction' => [
                    'trigger' => 'strike',
                    'window' => 'before_defense',
                    'performer' => 'third_party',
                ],
                'cover_ally' => [
                    'defense_advantages' => [
                        ['delta' => -1, 'source_code' => 'circumstances'],
                    ],
                    'reactions' => ['block'],
                    'resolution' => [
                        'success' => 'highest_rating',
                        'failure' => 'attacker_choice',
                        'becomes' => 'actual_target',
                    ],
                    'distance' => 'declared',
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('support-any', new RuleVersionBody('ability', 'Support any', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                ]],
                'reaction' => [
                    'trigger' => 'strike',
                    'window' => 'before_defense',
                    'performer' => 'third_party',
                ],
                'support' => ['sides' => ['attacker', 'defender'], 'advantage' => 1],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('block', new RuleVersionBody('ability', 'Block', '', [
                'type' => 'action',
                'combat_action' => 'block',
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                ]],
            ], [], [], 'needs_work')),
        ];
    }

    /**
     * Тред сообщения.
     *
     * @param MessageRecord $message Сообщение.
     *
     * @return array<string, mixed> Тред.
     */
    private function threadOf(MessageRecord $message): array
    {
        foreach ($message->getAttachments() as $attachment) {
            if (!is_array($attachment) || ($attachment['type'] ?? null) !== 'chat_system') {
                continue;
            }

            $payload = $attachment['payload'] ?? null;
            if (is_array($payload) && is_array($payload['thread'] ?? null)) {
                return $payload['thread'];
            }
        }

        return [];
    }

    /**
     * Текущее значение ресурса персонажа.
     *
     * @param int $characterId Персонаж.
     * @param string $code Код.
     *
     * @return int|null Значение.
     */
    private function actionPointsOf(int $characterId): ?int
    {
        $sheet = $this->characterFacade()->get($characterId)->getSheet();

        return $this->resourceCurrent(
            is_array($sheet['resources'] ?? null) ? $sheet['resources'] : [],
            'action-points',
        );
    }

    /**
     * Id каталога production pay_sr_vs_reliability.
     *
     * @param IMechanics $mechanics Каталог.
     *
     * @return int Id.
     */
    private function reliabilityCutId(IMechanics $mechanics): int
    {
        try {
            return $mechanics->add('pay_sr_vs_reliability', 'РУ против надёжности', '', '1.0.0');
        } catch (MechanicInvalidException) {
            return $mechanics->getByCodeVersion('pay_sr_vs_reliability', '1.0.0')->getId();
        }
    }

    /**
     * Кладёт метки в бой напрямую, не двигая версию.
     *
     * @param int $battleId Бой.
     * @param array<int, array<string, mixed>> $marks Метки.
     *
     * @return void
     */
    private function seedBattleMarks(int $battleId, array $marks): void
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $gateway->open(GameBattleTable::class)->records()->update($battleId, ['marks' => $marks]);
    }

    /**
     * Метка «помеха защите от ударов атакующего».
     *
     * @param int $subject Кому.
     * @param int $attacker Против кого.
     *
     * @return array<string, mixed> Метка.
     */
    private function battleMark(int $subject, int $attacker): array
    {
        return [
            'subject' => ['type' => 'character', 'id' => $subject],
            'kind' => 'advantage',
            'value' => -2,
            'source_code' => 'circumstances',
            'source_rule_code' => 'obman',
            'scope' => ['defense_against' => ['type' => 'character', 'id' => $attacker]],
            'consumes' => 'first_defense',
            'expires' => ['kind' => 'turn', 'of' => ['type' => 'character', 'id' => $attacker]],
        ];
    }

    /**
     * Строка боя.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed> Строка.
     */
    private function battleRow(int $battleId): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = $gateway->open(GameBattleTable::class)->records()->getById($battleId);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * Мир с оружием.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithWeapon(
        bool|int $penetrationMode = false,
        ?int $damageTypeCap = null,
        bool $hitConcentration = false,
        array $extraEntries = [],
        bool $withPendingEffects = false,
    ): RuleSpaceRecord {
        $withPenetration = $penetrationMode === true;
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $reliabilityCutId = $this->reliabilityCutId($mechanics);
        $world = $this->addWorldWithRevision(
            'razrabotka' . ($damageTypeCap === null ? '' : '-cap' . $damageTypeCap)
            . ($hitConcentration ? '-conc' : '')
            . ($withPendingEffects ? '-pending' : '')
            . ($extraEntries === []
                ? ''
                : '-ex' . substr(md5(implode(',', array_map(
                    static fn (RuleCommitEntry $entry): string => (string) $entry->getCode(),
                    $extraEntries,
                ))), 0, 6)),
        );
        $swordProfile = ['type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]], 'accuracy' => ['base' => 3, 'size' => 0]];
        if ($withPenetration) {
            $swordProfile = [
                'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                'accuracy' => ['base' => 3, 'size' => 0],
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
        $entries = [
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
                ], [
                    'characteristic_code' => 'will',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 3, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing', new RuleVersionBody('ability', 'Swing', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [],
                'attack_mode' => 'wide',
                'max_targets' => 4,
                ...($withPendingEffects ? ['action_effects' => [[
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['hit'],
                    'delta' => -2,
                ]]] : []),
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing1', new RuleVersionBody('ability', 'Swing one', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swingfx', new RuleVersionBody('ability', 'Swing fx', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [],
                'attack_mode' => 'wide',
                'max_targets' => 4,
                'action_effects' => [[
                    'type' => 'current_action_attack_characteristic_from_success_rating',
                    'floor_div' => 2,
                    'cap' => 3,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('widebroken', new RuleVersionBody('ability', 'Wide broken', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [],
                'attack_mode' => 'wide',
                'max_targets' => 1,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('wide-locked', new RuleVersionBody('ability', 'Wide locked', '', [
                'type' => 'action',
                'action_components' => [],
                'attack_mode' => 'wide',
                'max_targets' => 4,
                'zones' => [],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('wide-gated', new RuleVersionBody('ability', 'Wide gated', '', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => [],
                'attack_mode' => 'wide',
                'max_targets' => 4,
                'requirements' => [[
                    'level' => 1,
                    'requirements' => [['type' => 'has_ability', 'ability_code' => 'missing', 'min_level' => 1]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('dodge', new RuleVersionBody('ability', 'Dodge', '', [
                'type' => 'action',
                'combat_action' => 'dodge',
                'dodge_efficiency' => ['base' => 4, 'size' => -1],
                'dodge_soak' => 'agility',
                'action_components' => [],
                'distinct_weapons' => false,
                'same_weapon' => false,
                'lift_parent_max_weapons' => false,
                'peak_concentration' => false,
                'will_focus' => false,
                'long_tension' => false,
                'multiple' => false,
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
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
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
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
                        'damage' => ['damage_type_code' => 'cut'],
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('cut', new RuleVersionBody('damage_type', 'Cut', '', [
                'forms' => ['genitive' => 'cut', 'dative' => 'cut'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
                ...($damageTypeCap === null ? [] : ['max_success_rating' => $damageTypeCap]),
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
                'automatic' => ['value' => ['base' => 3, 'size' => 0]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strength', new RuleVersionBody('characteristic', 'Strength', '', [
                'weapon_mastery' => ['profiles' => ['strike']],
                'automatic' => ['value' => ['base' => 3, 'size' => 0]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('stamina', new RuleVersionBody('characteristic', 'Stamina', '', [
                'damage_endurance' => true,
                'automatic' => ['value' => ['base' => 4, 'size' => 0]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('hurt', new RuleVersionBody('state', 'Hurt', '', [
                'value_type' => 'dimensional',
                'damage_remainder' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('tired', new RuleVersionBody('state', 'Tired', '', [
                'value_type' => 'number',
                'aggregation' => 'sum',
                'damage_exhaustion' => true,
                'check_code' => 'will-check',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('will', new RuleVersionBody('characteristic', 'Will', '', [
                'willpower' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('weak', new RuleVersionBody('state', 'Weak', '', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
                'decline_weakness' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('disabled', new RuleVersionBody('state', 'Disabled', '', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
                'decline_disabled' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('unconscious', new RuleVersionBody('state', 'Unconscious', '', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
                'decline_unconscious' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('maim', new RuleVersionBody('state', 'Maim', '', [
                'value_type' => 'number',
                'aggregation' => 'max',
                'maim' => true,
                'check_code' => 'maim-check',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('will-check', new RuleVersionBody('check', 'Will check', '', [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => false,
                'concentration_token' => false,
                'willpower' => true,
                'unstable_check' => false,
                'hit_check' => false,
                'initiative' => false,
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'tired'],
            ], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => [
                    'type' => 'roll',
                    'data' => ['diceCount' => 1, 'dieFaces' => 1],
                    'outcomes' => [['maxRating' => -3, 'stateRuleCode' => 'weak']],
                ],
            ]], 'needs_work')),
            RuleCommitEntry::put('maim-check', new RuleVersionBody('check', 'Maim check', '', [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => false,
                'concentration_token' => false,
                'willpower' => false,
                'unstable_check' => false,
                'hit_check' => false,
                'initiative' => false,
                'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
            ], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
            ]], 'needs_work')),
            RuleCommitEntry::put('hit', new RuleVersionBody('check', 'Hit', '', [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => true,
                'concentration_token' => $hitConcentration,
                'willpower' => false,
                'unstable_check' => false,
                'hit_check' => true,
                'initiative' => false,
                'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
            ], [], [], 'needs_work')),
            ...$extraEntries,
        ];
        $this->worldRevision = $this->gameRuleSpaces()->commit($world->getId(), $entries)->getRevision();

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
     * Current ресурса по коду.
     *
     * @param array<int, mixed> $resources Строки листа.
     * @param string $code Код ресурса.
     *
     * @return int|null Current.
     */
    private function resourceCurrent(array $resources, string $code): ?int
    {
        foreach ($resources as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === $code) {
                return is_int($row['current'] ?? null) ? $row['current'] : null;
            }
        }

        return null;
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
     * Выбор атаки с явными целями.
     *
     * @param int $attackerId Атакующий.
     * @param list<array{type: string, id: int}> $targets Цели.
     *
     * @return array<string, mixed> Тело.
     */
    private function wideAttack(int $attackerId, array $targets): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'targets' => $targets,
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
    }

    /**
     * Тело защиты с явными целями.
     *
     * @param list<array{type: string, id: int}> $targets Цели.
     * @param list<array<string, mixed>> $choices Ответы.
     *
     * @return array{defenses: list<array<string, mixed>>} Тело.
     */
    private function defenses(array $targets, array $choices): array
    {
        foreach ($choices as $index => $choice) {
            $choices[$index]['target'] = $targets[$index];
        }

        return ['defenses' => $choices];
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
            'action' => array_key_exists('targets', $attack)
                ? $this->wideStrikeEnvelope($attack)
                : $this->strikeEnvelope($attack),
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
        $rows = (new GameActionStrikeRepository($gateway))->getList($strikeId);
        $targetId = $rows[$index]['id'] ?? null;
        self::assertIsInt($targetId);
        $mapped = [];
        if (array_key_exists('defender_kind', $values)) {
            $mapped['target_type'] = $values['defender_kind'];
        }

        if (array_key_exists('defender_id', $values)) {
            $mapped['target_id'] = $values['defender_id'];
        }

        foreach (['reaction', 'block_item_inventory_id', 'block_item_profile_index', 'block_item_rule_code'] as $key) {
            if (array_key_exists($key, $values)) {
                $mapped[$key] = $values[$key];
            }
        }

        $gateway->open(GameActionStrikeTable::class)->records()->update($targetId, $mapped);
    }

    /**
     * Черновик.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $status Статус.
     *
     * @return int Игра.
     */
    private function addGame(int $spaceId, int $rulesRevision, string $status = 'draft'): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => $status,
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
     * @param array<int, array<string, mixed>> $inventory Инвентарь.
     * @param bool $purchaseStrength Добавлять закупку силы по умолчанию.
     * @param int|null $ownerUserId Владелец персонажа.
     *
     * @return int Персонаж.
     */
    private function admit(
        int $spaceId,
        int $gameId,
        string $name,
        array $sheet = [],
        array $inventory = [],
        bool $purchaseStrength = true,
        ?int $ownerUserId = null,
        array $abilities = [],
    ): int {
        $ownerUserId ??= $this->ownerUserId;
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $ownerUserId,
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
        $this->characterFacade()->replaceMigrated(
            $characterId,
            $name,
            true,
            $this->storedChoices($name, $inventory, $purchaseStrength, $abilities),
            $document,
            2,
            1,
        );
        $this->membershipFacade()->submit($gameId, $characterId, $ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    /**
     * Учётка игрока.
     *
     * @param string $login Логин.
     *
     * @return int Пользователь.
     */
    private function addUser(string $login): int
    {
        return $this->gameUserAccounts()->addFromInput(['login' => $login, 'name' => $login]);
    }

    /**
     * Добавляет игрока в игру.
     *
     * @param int $gameId Игра.
     * @param int $userId Пользователь.
     *
     * @return void
     */
    private function addPlayer(int $gameId, int $userId): void
    {
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $userId,
            'role' => 'player',
        ]));
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
    private function storedChoices(
        string $name,
        array $inventory = [],
        bool $purchaseStrength = true,
        array $abilities = [],
    ): array {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => $abilities,
            'inventory' => $this->inventoryWithIds($inventory === [] ? [
                ['ruleCode' => 'sword', 'equipped' => true],
            ] : $inventory),
            'characteristicPurchases' => $purchaseStrength
                ? [['characteristicCode' => 'strength', 'cost' => 2]]
                : [],
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

    /**
     * Чат игры.
     *
     * @return IChats Сервис.
     */
    private function chats(): IChats
    {
        $chats = $this->gameApplication()->getLocator()->get(IChatContainer::class)->get(IChats::class);
        self::assertInstanceOf(IChats::class, $chats);

        return $chats;
    }

    /**
     * Сообщения игрового чата в хронологическом порядке.
     *
     * @param int $gameId Игра.
     *
     * @return array<int, MessageRecord> Сообщения.
     */
    private function chatMessages(int $gameId): array
    {
        $chatId = $this->gameFacade()->get($gameId)->getGameChatId();
        self::assertIsInt($chatId);
        $messages = array_reverse($this->chats()->findMessagePage($chatId, $this->ownerUserId, 100, 0)->getItems());

        return array_values(array_filter(
            $messages,
            fn (MessageRecord $message): bool => $this->threadKind($message) === 'attack',
        ));
    }

    /**
     * Вид треда сообщения.
     *
     * @param MessageRecord $message Сообщение.
     *
     * @return string|null Вид или null.
     */
    private function threadKind(MessageRecord $message): ?string
    {
        foreach ($message->getAttachments() as $attachment) {
            if (($attachment['type'] ?? null) !== 'chat_system') {
                continue;
            }
            $thread = $attachment['payload']['thread'] ?? null;
            if (is_array($thread) && is_string($thread['kind'] ?? null)) {
                return $thread['kind'];
            }
        }

        return null;
    }

    /**
     * Вложения сообщения без служебных.
     *
     * @param MessageRecord $message Сообщение.
     *
     * @return array<int, array{type: string, payload: mixed}> Вложения.
     */
    private function contentAttachments(MessageRecord $message): array
    {
        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            if (in_array($attachment['type'] ?? null, ['chat_system', 'chat_speaker'], true)) {
                continue;
            }
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * Payload-ы вложений заданного типа.
     *
     * @param array<int, MessageRecord> $messages Сообщения.
     * @param string $type Тип вложения.
     *
     * @return array<int, mixed> Payload-ы.
     */
    private function attachmentsOfType(array $messages, string $type): array
    {
        $found = [];
        foreach ($messages as $message) {
            foreach ($message->getAttachments() as $attachment) {
                if (($attachment['type'] ?? null) === $type) {
                    $found[] = $attachment['payload'] ?? null;
                }
            }
        }

        return $found;
    }

    /**
     * Строки outbox.
     *
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
}
