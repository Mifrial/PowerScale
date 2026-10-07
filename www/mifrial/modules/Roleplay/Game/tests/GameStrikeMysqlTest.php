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
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Game\Table\GameStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;

final class GameStrikeMysqlTest extends TestCase
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
     * Атака пишет выбор и не меняет лист. Защита закрывает удар.
     *
     * @return void
     */
    public function testDeclareAndResolveLeavesSheet(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        $npcActual = $npc['data']['actualVersion'];
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'attack' => $this->attack($characterId, $npc['data']['npcId']),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(2, $opened['data']['version']);
        self::assertNull($opened['data']['sheetVersion']);
        self::assertSame(1, $this->countAll(GameStrikeTable::class));
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame($npcActual, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['data']['npcId'],
        ])['data']['actualVersion']);
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npcActual,
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($npcActual + 1, $closed['data']['sheetVersion']);
        self::assertSame(1, $closed['data']['success']);
        self::assertSame($this->pair(0), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame(3, $closed['data']['version']);
        self::assertSame($npcActual + 1, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['data']['npcId'],
        ])['data']['actualVersion']);
        $replay = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npcActual,
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertEquals($closed['data'], $replay['data']);
        self::assertSame(1, $this->countAll(GameStrikeTable::class));
    }

    /**
     * Закрытие возвращает число формулы профиля по характеристике атакующего.
     *
     * @return void
     */
    public function testResolveUsesProfileFormula(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [[
            'characteristicCode' => 'strength',
            'cost' => 2,
            'value' => ['base' => 4, 'size' => 0],
        ]]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        $attack = $this->attack($characterId, $npc['data']['npcId']);
        $attack['itemRuleCode'] = 'axe';
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'attack' => $attack,
        ])['success']);
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(1, $closed['data']['success']);
        self::assertSame($this->pair(4), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame($this->pair(4), $closed['data']['injury']);
        self::assertArrayNotHasKey('S', $closed['data']);
        self::assertIsInt($closed['data']['sheetVersion']);
    }

    /**
     * Нет карточки попадания, две карточки или пул без числа — удар открыт.
     *
     * @return void
     */
    /**
     * Сопротивление — сумма слота брони того же типа, не урон и не лист.
     *
     * @return void
     */
    public function testResolveSumsTargetResistance(): void
    {
        $world = $this->worldWithWeapon();
        $closed = $this->resolveDefense($world->getId(), [], 'blade', true, 'ignore', false, [[
            'id' => 1,
            'ruleCode' => 'mail',
            'equipped' => true,
        ]]);
        self::assertSame($this->pair(0), $closed['damage']);
        self::assertSame(1, $closed['success']);
        self::assertSame($this->pair(3), $closed['resistance']);
        self::assertSame($this->pair(0), $closed['injury']);
        self::assertIsInt($closed['sheetVersion']);
        self::assertArrayNotHasKey('S', $closed);
    }

    /**
     * dodge пишет S с пользы профиля. Урон, рейтинг и сопротивление те же.
     *
     * @return void
     */
    public function testResolveWritesDodgeSoak(): void
    {
        $world = $this->worldWithWeapon(1, true, 1);
        $purchase = [[
            'characteristicCode' => 'agility',
            'cost' => 2,
            'value' => ['base' => 4, 'size' => 0],
        ]];
        $closed = $this->resolveDefense($world->getId(), [
            'characteristicPurchases' => $purchase,
        ], 'rapier', true, 'dodge', true);
        self::assertSame($this->pair(0), $closed['damage']);
        self::assertSame(1, $closed['success']);
        self::assertSame($this->pair(0), $closed['resistance']);
        self::assertSame($this->pair(3), $closed['S']);
        self::assertSame($this->pair(0), $closed['injury']);
        self::assertIsInt($closed['sheetVersion']);
    }

    /**
     * Пустая польза — −3. Явный ноль не подменяется.
     *
     * @return void
     */
    public function testResolveUsesFallbackAndExplicitZeroBenefit(): void
    {
        $world = $this->worldWithWeapon(1, true, 1);
        $sheet = ['characteristicPurchases' => [[
            'characteristicCode' => 'agility',
            'cost' => 2,
            'value' => ['base' => 4, 'size' => 0],
        ]]];
        $fallback = $this->resolveDefense($world->getId(), $sheet, 'sword', true, 'dodge');
        $zero = $this->resolveDefense($world->getId(), $sheet, 'wand', true, 'dodge');
        self::assertSame($this->pair(4, -1), $fallback['S']);
        self::assertSame($this->pair(4), $zero['S']);
        self::assertSame($this->pair(0), $fallback['damage']);
        self::assertSame($this->pair(0), $zero['damage']);
    }

    /**
     * ignore и block ключ S не пишут и без карточки закрываются.
     *
     * @return void
     */
    public function testResolveOmitsSoakUnlessDodge(): void
    {
        $world = $this->worldWithWeapon();
        $ignored = $this->resolveDefense($world->getId(), [], 'sword', true, 'ignore');
        $blocked = $this->resolveDefense($world->getId(), [], 'sword', true, 'block');
        self::assertArrayNotHasKey('S', $ignored);
        self::assertArrayNotHasKey('S', $blocked);
        self::assertSame($this->pair(0), $ignored['injury']);
        self::assertSame($this->pair(0), $blocked['injury']);
        self::assertSame($this->pair(0), $ignored['damage']);
    }

    /**
     * Нет карточки, две карточки или нет закупки — удар открыт.
     *
     * @return void
     */
    public function testResolveRejectsDodgeWithoutCharacteristic(): void
    {
        $none = $this->worldWithWeapon();
        $two = $this->worldWithWeapon(1, true, 2);
        $one = $this->worldWithWeapon(1, true, 1);
        self::assertSame('GAME_INVALID', $this->resolveDefense($none->getId(), [], 'sword', false, 'dodge')['error']['code']);
        self::assertSame('GAME_INVALID', $this->resolveDefense($two->getId(), [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'cost' => 2,
                'value' => ['base' => 4, 'size' => 0],
            ]],
        ], 'sword', false, 'dodge')['error']['code']);
        self::assertSame('GAME_INVALID', $this->resolveDefense($one->getId(), [
            'characteristicPurchases' => [],
        ], 'sword', false, 'dodge')['error']['code']);
    }

    /**
     * Нет слота этого типа — ноль, удар закрывается.
     *
     * @return void
     */
    public function testResolveUsesZeroResistanceWithoutSlot(): void
    {
        $world = $this->worldWithWeapon();
        $spaceId = $world->getId();
        $empty = $this->resolveDefense($spaceId, ['equippedModifiers' => []], 'blade');
        $bare = $this->resolveDefense($spaceId, [], 'blade');
        $weapon = $this->resolveDefense($spaceId, ['equippedModifiers' => [[
            'ruleCode' => 'sword',
        ]]], 'blade');
        $other = $this->resolveDefense($spaceId, ['equippedModifiers' => [[
            'ruleCode' => 'cloth',
        ]]], 'blade');
        $plain = $this->resolveDefense($spaceId, ['equippedModifiers' => [[
            'ruleCode' => 'plate',
        ]]], 'blade');
        $untyped = $this->resolveDefense($spaceId, ['equippedModifiers' => [[
            'ruleCode' => 'mail',
        ]]], 'sword');
        self::assertSame($this->pair(0), $empty['resistance']);
        self::assertSame($this->pair(0), $bare['resistance']);
        self::assertSame($this->pair(0), $weapon['resistance']);
        self::assertSame($this->pair(0), $other['resistance']);
        self::assertSame($this->pair(0), $plain['resistance']);
        self::assertSame($this->pair(0), $untyped['resistance']);
        self::assertSame($this->pair(0), $untyped['damage']);
    }

    /**
     * Мёртвый код инвентаря не входит в сессию, бой с ним не стартует.
     *
     * @return void
     */
    public function testResolveRejectsDeadEquippedCode(): void
    {
        $world = $this->worldWithWeapon();
        $spaceId = $world->getId();
        self::assertSame('GAME_NOT_FOUND', $this->battleCode($spaceId, [[
            'id' => 1,
            'ruleCode' => 'ghost',
            'equipped' => true,
        ]]));
        self::assertSame('GAME_NOT_FOUND', $this->battleCode($spaceId, [[
            'id' => 1,
            'ruleCode' => 'swing',
            'equipped' => true,
        ]]));
        self::assertSame('GAME_NOT_FOUND', $this->battleCode($spaceId, [[
            'id' => 1,
            'ruleCode' => 'junk',
            'equipped' => true,
        ]]));
    }

    public function testResolveRejectsHitSlice(): void
    {
        $this->assertOpenAfterHitRefusal($this->worldWithWeapon(0));
        $this->assertOpenAfterHitRefusal($this->worldWithWeapon(2));
        $this->assertOpenAfterHitRefusal($this->worldWithWeapon(1, false));
    }

    /**
     * Чужой профиль, урон во входе, чужая версия и stop.
     *
     * @return void
     */
    public function testRejectsBadProfileStaleVersionAndClearsOnStop(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), 2);
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battleId = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ])['data']['battleId'];
        $attack = $this->attack($characterId, $npc['data']['npcId']);
        $attack['itemRuleCode'] = 'buckler';
        $bad = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'bad',
            'expectedVersion' => 1,
            'attack' => $attack,
        ]);
        self::assertSame('GAME_INVALID', $bad['error']['code']);
        self::assertSame(0, $this->countAll(GameStrikeTable::class));
        $damage = $this->attack($characterId, $npc['data']['npcId']);
        $damage['actionPointCost'] = 1;
        $rejected = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'dmg',
            'expectedVersion' => 1,
            'attack' => $damage,
        ]);
        self::assertSame('INVALID_PARAMS', $rejected['error']['code']);
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'attack' => $this->attack($characterId, $npc['data']['npcId']),
        ]);
        self::assertTrue($opened['success']);
        $stale = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'stale',
            'expectedVersion' => 1,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'dodge'],
        ]);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        self::assertSame(1, $this->countWhere(GameStrikeTable::class, 'open', true));
        $ended = $this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'e1',
            'expectedVersion' => 2,
        ]);
        self::assertTrue($ended['success']);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($card['data']['sessionRunning']);
        self::assertTrue($this->dispatch('game.stopSession', ['gameId' => $gameId])['success']);
        self::assertSame(0, $this->countAll(GameStrikeTable::class));
        self::assertSame(0, $this->countAll(GameStrikeCommandTable::class));
        self::assertSame(0, $this->countAll(GameSessionTable::class));
    }

    /**
     * Закрытие без единственной бросаемой карточки не пишет исход.
     *
     * @param RuleSpaceRecord $world Мир.
     *
     * @return void
     */
    private function assertOpenAfterHitRefusal(RuleSpaceRecord $world): void
    {
        $gameId = $this->addGame($world->getId(), 2);
        $characterId = $this->admit($world->getId(), $gameId, 'Hero');
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-' . $gameId,
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-' . $gameId,
            'expectedVersion' => 1,
            'attack' => $this->attack($characterId, $npc['data']['npcId']),
        ])['success']);
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-' . $gameId,
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertSame('GAME_INVALID', $closed['error']['code']);
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame($npc['data']['actualVersion'], $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['data']['npcId'],
        ])['data']['actualVersion']);
    }

    /**
     * Мир с оружием и щитом без профиля атаки.
     *
     * @param int $hitCards Сколько карточек попадания.
     * @param bool $withPool Есть ли число кубов и граней.
     * @param int $soakCards Сколько характеристик уклонения.
     *
     * @return RuleSpaceRecord Мир.
     */
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
     * Мир с оружием и щитом без профиля атаки.
     *
     * @param int $hitCards Сколько карточек попадания.
     * @param bool $withPool Есть ли число кубов и граней.
     * @param int $soakCards Сколько характеристик уклонения.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithWeapon(int $hitCards = 1, bool $withPool = true, int $soakCards = 0): RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $world = $this->addWorldWithRevision('razrabotka-' . $hitCards . ($withPool ? '-pool' : '-empty') . '-s' . $soakCards);
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => true,
            'concentration_token' => false,
            'willpower' => false,
            'unstable_check' => false,
            'hit_check' => true,
            'initiative' => false,
            'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
        ];
        $payload = $withPool
            ? ['diceCount' => 1, 'dieFaces' => 1]
            : [];
        $entries = [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => $this->raceCharacteristics($soakCards),
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
            RuleCommitEntry::put('axe', new RuleVersionBody('item', 'Axe', '', [
                'category' => 'weapon',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'damage' => [
                            'formula' => [
                                'type' => 'characteristic',
                                'characteristic_code' => 'strength',
                                'modifier' => 0,
                            ],
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
            RuleCommitEntry::put('crush', new RuleVersionBody('damage_type', 'Crush', '', [
                'forms' => ['genitive' => 'crush', 'dative' => 'crush'],
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
                        'damage_type_code' => 'crush',
                        'value' => ['base' => 2, 'size' => 0],
                        'durability' => 1,
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('plate', new RuleVersionBody('item', 'Plate', '', [
                'category' => 'armor',
                'armor' => ['strength_penalty' => 1],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('junk', new RuleVersionBody('item', 'Junk', '', [
                'category' => 'armor',
                'armor' => ['resistance_slots' => 'nope'],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('buckler', new RuleVersionBody('item', 'Buckler', '', [
                'category' => 'shield',
                'shield' => [],
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
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => $payload],
            ]], 'needs_work')),
        ];
        if ($soakCards >= 1) {
            $entries[] = RuleCommitEntry::put('agility', new RuleVersionBody('characteristic', 'Agility', '', [
                'dodge_soak' => true,
            ], [], [], 'needs_work'));
            $entries[] = RuleCommitEntry::put('rapier', new RuleVersionBody('item', 'Rapier', '', [
                'category' => 'weapon',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'dodge_benefit' => -1,
                    ]],
                ],
            ], [], [], 'needs_work'));
            $entries[] = RuleCommitEntry::put('wand', new RuleVersionBody('item', 'Wand', '', [
                'category' => 'weapon',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'dodge_benefit' => 0,
                    ]],
                ],
            ], [], [], 'needs_work'));
        }

        if ($soakCards >= 2) {
            $entries[] = RuleCommitEntry::put('balance', new RuleVersionBody('characteristic', 'Balance', '', [
                'dodge_soak' => true,
            ], [], [], 'needs_work'));
        }

        if ($hitCards >= 1) {
            $entries[] = RuleCommitEntry::put('hit', new RuleVersionBody('check', 'Hit', '', $check, [], [], 'needs_work'));
        }

        if ($hitCards >= 2) {
            $entries[] = RuleCommitEntry::put('hit-again', new RuleVersionBody('check', 'Hit again', '', [
                ...$check,
                'ordinary_root' => false,
            ], [], [], 'needs_work'));
        }

        $this->gameRuleSpaces()->commit($world->getId(), $entries);

        return $world;
    }

    /**
     * Выбор атаки мечом.
     *
     * @param int $characterId Персонаж.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Тело.
     */
    private function attack(int $characterId, int $npcId): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $characterId],
            'defender' => ['type' => 'npc', 'id' => $npcId],
            'actionRuleCode' => 'swing',
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
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
     * @param array<int, array<string, mixed>> $purchases Закупки листа.
     * @param array<string, mixed> $sheet Дополнение листа.
     *
     * @return int Персонаж.
     */
    /**
     * Закрытие удара по персонажу с заданным листом.
     *
     * @param int $spaceId Мир.
     * @param array<string, mixed> $sheet Дополнение листа цели.
     * @param string $itemRuleCode Предмет атаки.
     * @param bool $expectClose Ждать успех.
     *
     * @return array<string, mixed> data или весь конверт отказа.
     */
    private function resolveDefense(
        int $spaceId,
        array $sheet,
        string $itemRuleCode,
        bool $expectClose = true,
        string $reaction = 'ignore',
        bool $replay = false,
        array $inventory = [],
    ): array {
        $gameId = $this->addGame($spaceId, 2);
        $attackerId = $this->admit($spaceId, $gameId, 'Hero-' . $itemRuleCode . '-' . $gameId);
        $defenderId = $this->admit($spaceId, $gameId, 'Guard-' . $gameId, [], $sheet, $inventory);
        $defenderVersion = $this->characterFacade()->get($defenderId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-resist',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-resist',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemRuleCode' => $itemRuleCode,
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-resist',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => $reaction === 'block'
                ? ['reaction' => 'block', 'blockItemRuleCode' => 'sword']
                : ['reaction' => $reaction],
        ]);
        if (!$expectClose) {
            return $closed;
        }

        self::assertTrue($closed['success'], json_encode($closed));
        if ($replay) {
            $again = $this->dispatch('game.resolveStrike', [
                'gameId' => $gameId,
                'battleId' => $battle['data']['battleId'],
                'idempotencyKey' => 'd-resist',
                'expectedVersion' => 2,
                'expectedSheetVersion' => $defenderVersion,
                'defense' => ['reaction' => $reaction],
            ]);
            self::assertEquals($closed['data'], $again['data']);
        }

        return $closed['data'];
    }

    /**
     * Код отказа старта боя, если цель с таким инвентарём не входит в сессию.
     *
     * @param int $spaceId Мир.
     * @param array<mixed> $inventory Инвентарь цели.
     *
     * @return string Код ошибки.
     */
    private function battleCode(int $spaceId, array $inventory): string
    {
        $gameId = $this->addGame($spaceId, 2);
        $attackerId = $this->admit($spaceId, $gameId, 'Hero-dead-' . $gameId);
        $defenderId = $this->admit($spaceId, $gameId, 'Guard-dead-' . $gameId, [], [], $inventory);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-dead',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);

        return is_string($battle['error']['code'] ?? null) ? $battle['error']['code'] : '';
    }

    /**
     * Лестница расы. Ловкость добавляется только вместе с карточкой уклонения.
     *
     * @param int $soakCards Сколько характеристик уклонения.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function raceCharacteristics(int $soakCards): array
    {
        $rows = [[
            'characteristic_code' => 'strength',
            'mode' => 'purchased',
            'base' => ['base' => 3, 'size' => 0],
            'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
        ]];
        if ($soakCards >= 1) {
            $rows[] = [
                'characteristic_code' => 'agility',
                'mode' => 'purchased',
                'base' => ['base' => 3, 'size' => 0],
                'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
            ];
        }

        return $rows;
    }

    private function admit(
        int $spaceId,
        int $gameId,
        string $name,
        array $purchases = [],
        array $sheet = [],
        array $inventory = [],
    ): int
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
            'characteristicPurchases' => $purchases,
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
            'limits' => [],
            'money' => 0,
            'active' => true,
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
     * Число строк.
     *
     * @param class-string $table Класс.
     *
     * @return int Число.
     */
    /**
     * Кладёт закупку стойкости на снимок NPC и поднимает версию.
     *
     * @param int $gameId Игра.
     * @param array<string, mixed> $npc Ответ создания.
     *
     * @return array<string, mixed> Тот же ответ с новой версией.
     */
    private function endureNpc(int $gameId, array $npc): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $npcId = $npc['data']['npcId'] ?? null;
        self::assertIsInt($npcId);
        $repo = new GameNpcRepository($gateway);
        $record = $repo->getById($npcId);
        self::assertSame($gameId, $record->getGameId());
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
        $saved = $repo->replaceVersion($npcId, $version, $record->getActualVersion());
        $npc['data']['actualVersion'] = $saved->getActualVersion();

        return $npc;
    }

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
     * Число строк по равенству.
     *
     * @param class-string $table Класс.
     * @param string $column Колонка.
     * @param int|bool $value Значение.
     *
     * @return int Число.
     */
    private function countWhere(string $table, string $column, int|bool $value): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return count($gateway->open($table)->records()->getList(new ListQuery(
            new FilterGroup('AND', [new FilterCondition($column, '=', $value)]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows());
    }
}
