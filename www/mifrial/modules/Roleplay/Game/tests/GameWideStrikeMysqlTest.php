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

/**
 * Широкий удар 1 → N. У принятой цели рейтинг попадания и число урона.
 */
final class GameWideStrikeMysqlTest extends TestCase
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
     * Две цели, пустые числа, отказ одной не затирает другую.
     *
     * @return void
     */
    public function testTwoTargetsKeepSeparateResults(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
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
        self::assertArrayNotHasKey('targetResults', $staleBattle['data'] ?? []);
        $closed = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion'] + 1],
                ['reaction' => 'dodge', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame('GAME_CONFLICT', $closed['data']['targetResults'][0]['code']);
        self::assertNull($closed['data']['targetResults'][0]['success']);
        self::assertNull($closed['data']['targetResults'][0]['damage']);
        self::assertNull($closed['data']['targetResults'][0]['resistance']);
        self::assertNull($closed['data']['targetResults'][0]['injury']);
        self::assertNull($closed['data']['targetResults'][1]['code']);
        self::assertSame(1, $closed['data']['targetResults'][1]['success']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['damage']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['resistance']);
        self::assertSame($this->pair(4, -1), $closed['data']['targetResults'][1]['S']);
        self::assertSame($this->pair(0), $closed['data']['targetResults'][1]['injury']);
        self::assertArrayNotHasKey('S', $closed['data']['targetResults'][0]);
        self::assertSame($first['actualVersion'], $this->npcVersion($gameId, $first['npcId']));
        $replay = $this->dispatch('game.resolveWideStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'defense' => ['defenses' => [
                ['reaction' => 'ignore', 'expectedSheetVersion' => $first['actualVersion'] + 1],
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
                ['reaction' => 'block', 'blockItemRuleCode' => 'buckler', 'expectedSheetVersion' => $first['actualVersion']],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $second['actualVersion']],
            ]],
        ]);
        self::assertSame('GAME_CONFLICT', $other['error']['code']);
    }

    /**
     * endBattle сессию не гасит. Stop снимает строки широкого удара.
     *
     * @return void
     */
    public function testEndBattleKeepsSessionAndStopClearsWideStrike(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
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
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero');
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
        self::assertTrue($this->dispatch('game.declareWideStrike', $this->declareBody(
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
                'itemRuleCode' => 'pike',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ))['success']);
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
        self::assertSame($this->pair(6), $closed['data']['targetResults'][1]['damage']);
        self::assertSame($this->pair(2, -1), $closed['data']['targetResults'][0]['injury']);
        self::assertArrayNotHasKey('S', $closed['data']['targetResults'][1]);
        self::assertSame($this->pair(4), $closed['data']['targetResults'][1]['injury']);
        self::assertSame($mailVersion + 1, $this->characterFacade()->get($mailId)->getActualVersion());
    }

    /**
     * Две цели несут разное сопротивление. Мёртвый код брони не закрывает удар.
     *
     * @return void
     */
    public function testTargetsKeepOwnResistance(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero');
        $mailId = $this->admit($world->getId(), $gameId, 'Mail', [], [[
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
                ['reaction' => 'ignore', 'expectedSheetVersion' => $mailVersion],
                ['reaction' => 'ignore', 'expectedSheetVersion' => $clothVersion],
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
     * Мир с оружием.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithWeapon(): RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
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
                'weapon' => [
                    'weapon_profiles' => [
                        ['type' => 'strike'],
                    ],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('pike', new RuleVersionBody('item', 'Pike', '', [
                'category' => 'weapon',
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
                'shield' => [],
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
        ]);

        return $world;
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
            'actualVersion' => $saved['data']['actualVersion'],
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
        $bought[] = ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]];
        $sheet['characteristicPurchases'] = $bought;
        if (!array_key_exists('abilityLevels', $sheet)) {
            $sheet['abilityLevels'] = [];
        }
        $version['sheet'] = $sheet;

        return [
            'npcId' => $npcId,
            'actualVersion' => $repo->replaceVersion($npcId, $version, $record->getActualVersion())->getActualVersion(),
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
            'rulesRevision' => 1,
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
            'inventory' => $inventory,
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'money' => 0,
            'active' => true,
        ];
    }
}
