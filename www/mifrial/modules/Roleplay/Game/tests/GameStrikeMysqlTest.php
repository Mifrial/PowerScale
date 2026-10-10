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
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Game\Table\GameStrikeCommandTable;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Service\MechanicPortFactory;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class GameStrikeMysqlTest extends TestCase
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
     * Атака пишет выбор и не меняет лист. Защита закрывает удар.
     *
     * @return void
     */
    public function testDeclareAndResolveLeavesSheet(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
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
        self::assertNull($closed['data']['sheetVersion']);
        self::assertSame(0, $closed['data']['success']);
        self::assertSame($this->pair(0), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame(2, $closed['data']['version']);
        self::assertSame($npcActual, $this->dispatch('game.getNpc', [
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
        $differentBody = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npcActual,
            'defense' => ['reaction' => 'dodge'],
        ]);
        self::assertSame('GAME_CONFLICT', $differentBody['error']['code']);
    }

    /**
     * Published damage types resolve reliability through live Rule and catalog.
     *
     * @return void
     */
    public function testCanonicalDamageTypesResolvePublishedReliabilityBinding(): void
    {
        $world = $this->worldWithWeapon();
        $revision = $this->worldRevision();
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $engine = (new MechanicPortFactory())->createEngine();

        foreach (['piercing', 'cutting', 'slashing'] as $damageTypeCode) {
            $rule = $this->gameRuleSpaces()->findInRevision($world->getId(), $revision, $damageTypeCode);
            $rows = $rule->getMechanics();
            self::assertCount(1, $rows);
            self::assertIsInt($rows[0]['mechanic_id'] ?? null);
            self::assertSame([], $rows[0]['mechanic_payload'] ?? null);

            $mechanicId = $rows[0]['mechanic_id'];
            self::assertSame(
                'reliability_cut',
                $mechanics->get($mechanicId)->getCode(),
            );
            self::assertSame(
                '1.0.0',
                $mechanics->get($mechanicId)->getHandlerVersion(),
            );
            self::assertTrue($engine->hasReliabilityCut(
                [new MechanicBinding($damageTypeCode, $mechanicId, null)],
                [$mechanics->get($mechanicId)],
                new ResolveActiveOptions(),
            ));
        }
    }

    /**
     * Две concurrent execution одного resolve дают один commit и replay.
     *
     * @return void
     */
    public function testConcurrentSameKeyResolveReplaysCommittedResult(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for concurrent replay acceptance');
        }

        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Concurrent-Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Concurrent-Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        $npcVersion = $npc['data']['actualVersion'];
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concurrent-battle',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'concurrent-open',
            'expectedVersion' => 1,
            'attack' => $this->attack($characterId, $npc['data']['npcId']),
        ])['success']);
        $payload = [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'concurrent-close',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npcVersion,
            'defense' => ['reaction' => 'ignore'],
        ];
        $deliveryBefore = $this->countAll(GameDeliveryTable::class);
        $commandsBefore = $this->countAll(GameStrikeCommandTable::class);

        $results = $this->runConcurrentStrikeRequests('game.resolveStrike', $payload);
        $this->reconnectGameAfterFork();

        self::assertCount(2, $results);
        self::assertEquals($results[0]['data'], $results[1]['data']);
        self::assertTrue($results[0]['success']);
        self::assertTrue($results[1]['success']);
        self::assertSame($commandsBefore + 1, $this->countAll(GameStrikeCommandTable::class));
        self::assertSame(1, $this->countAll(GameStrikeTable::class));
        self::assertSame($npcVersion, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['data']['npcId'],
        ])['data']['actualVersion']);
        self::assertSame($deliveryBefore + 1, $this->countAll(GameDeliveryTable::class));

        $sameBodyReplay = $this->dispatch('game.resolveStrike', $payload);
        self::assertTrue($sameBodyReplay['success']);
        self::assertEquals($results[0]['data'], $sameBodyReplay['data']);

        $differentBody = $payload;
        $differentBody['defense'] = ['reaction' => 'dodge'];
        $differentBodyResult = $this->dispatch('game.resolveStrike', $differentBody);
        self::assertFalse($differentBodyResult['success']);
        self::assertSame('GAME_CONFLICT', $differentBodyResult['error']['code']);
        self::assertSame($commandsBefore + 1, $this->countAll(GameStrikeCommandTable::class));
        self::assertSame(1, $this->countAll(GameStrikeTable::class));
        self::assertSame($deliveryBefore + 1, $this->countAll(GameDeliveryTable::class));
    }

    /**
     * Закрытие возвращает число формулы профиля по характеристике атакующего.
     *
     * @return void
     */
    public function testResolveUsesProfileFormula(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [[
            'characteristicCode' => 'strength',
            'cost' => 2,
            'value' => ['base' => 4, 'size' => 0],
        ]], [], [['ruleCode' => 'axe', 'equipped' => true]]);
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
            'defense' => [
                'reaction' => 'block',
                'blockItemInventoryId' => 1,
                'blockItemProfileIndex' => 0,
                'blockItemRuleCode' => 'sword',
            ],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(4, $closed['data']['success']);
        self::assertSame($this->pair(4), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame($this->pair(16), $closed['data']['injury']);
        self::assertArrayNotHasKey('S', $closed['data']);
        self::assertIsInt($closed['data']['sheetVersion']);
    }

    /**
     * Single penetration использует live profile, выбранный instance и modifier.
     *
     * @return void
     */
    public function testSingleLivePenetrationUsesSelectedModifierAndKeepsJsonShape(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 1, 'size' => 0]],
        ], [], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => []],
            ['id' => 2, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => ['penetrator']],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Plate', [
            ['characteristicCode' => 'agility', 'value' => ['base' => 3, 'size' => 0]],
        ], [], [['id' => 1, 'ruleCode' => 'plate', 'equipped' => true]]);
        $defenderVersion = $this->characterFacade()->get($defenderId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'single-penetration-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $attack = [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'defender' => ['type' => 'character', 'id' => $defenderId],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 2,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'single-penetration-open',
            'expectedVersion' => 1,
            'attack' => $attack,
        ])['success']);
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'single-penetration-close',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => ['reaction' => 'dodge'],
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($this->pair(6), $closed['data']['damage']);
        self::assertSame($this->pair(2), $closed['data']['resistance']);
        self::assertSame($this->pair(5, -1), $closed['data']['injury']);
        self::assertSame(
            ['battleId', 'strikeId', 'version', 'attackerRoll', 'success', 'damage', 'resistance', 'sheetVersion', 'S', 'injury'],
            array_keys($closed['data']),
        );
        self::assertSame($defenderVersion + 1, $this->characterFacade()->get($defenderId)->getActualVersion());
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
        $world = $this->worldWithWeapon(1, true, 1);
        $closed = $this->resolveDefense($world->getId(), [
            'characteristicPurchases' => [[
                'characteristicCode' => 'agility',
                'cost' => 2,
                'value' => ['base' => 4, 'size' => 0],
            ]],
        ], 'blade', true, 'dodge', false, [[
            'id' => 1,
            'ruleCode' => 'mail',
            'equipped' => true,
        ]]);
        self::assertSame($this->pair(0), $closed['damage']);
        self::assertSame(3, $closed['success']);
        self::assertSame($this->pair(3), $closed['resistance']);
        self::assertSame($this->pair(0), $closed['injury']);
        self::assertIsInt($closed['sheetVersion']);
        self::assertArrayHasKey('S', $closed);
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
        self::assertSame(3, $closed['success']);
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
        $blocked = $this->resolveDefense($world->getId(), [], 'sword', true, 'ignore');
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
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
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
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npc['data']['actualVersion'] + 1,
            'defense' => ['reaction' => 'dodge'],
        ]);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        self::assertArrayHasKey('choices', $stale['error']['details']);
        self::assertArrayHasKey('sheet', $stale['error']['details']);
        self::assertArrayNotHasKey('currentSheet', $stale['error']['details']);
        $staleBattle = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'stale-battle',
            'expectedVersion' => 1,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'dodge'],
        ]);
        self::assertSame('GAME_CONFLICT', $staleBattle['error']['code']);
        self::assertArrayNotHasKey('choices', $staleBattle['error']['details']);
        self::assertArrayNotHasKey('sheet', $staleBattle['error']['details']);
        self::assertSame(1, $this->countWhere(GameStrikeTable::class, 'open', true));
        self::assertSame(1, $this->countAll(GameStrikeCommandTable::class));
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
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
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
     * Mutation-time CAS single penetration откатывает лист, удар, бой и ресурс.
     *
     * @return void
     */
    public function testSinglePenetrationMutationCasRollsBackSheetStrikeBattleAndResources(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 1, 'size' => 0]],
        ], [], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => []],
            ['id' => 2, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => ['penetrator']],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Plate', [
            ['characteristicCode' => 'agility', 'value' => ['base' => 3, 'size' => 0]],
        ], [], [['id' => 1, 'ruleCode' => 'plate', 'equipped' => true]]);
        $defender = $this->characterFacade()->get($defenderId);
        $attackerVersion = $this->characterFacade()->get($attackerId)->getActualVersion();
        $defenderVersion = $defender->getActualVersion();
        $defenderSheet = $defender->getSheet();
        $defenderChoices = $defender->getChoices();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'single-cas-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'single-cas-open',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ])['success']);
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $strike = $mysql->select('select item_inventory_id, item_rule_code, open from game_strike');
        self::assertSame(2, (int) $strike[0]->item_inventory_id);
        self::assertSame('sword', (string) $strike[0]->item_rule_code);
        $commandsBefore = $this->countAll(GameStrikeCommandTable::class);
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, [$defenderId]);
        try {
            $closed = $this->dispatch('game.resolveStrike', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'single-cas-close',
                'expectedVersion' => 2,
                'expectedSheetVersion' => $defenderVersion,
                'defense' => ['reaction' => 'dodge'],
            ]);
        } finally {
            $probe->disarm();
        }

        self::assertSame('GAME_CONFLICT', $closed['error']['code'] ?? null, json_encode($closed));
        self::assertSame($defenderVersion + 1, $closed['error']['details']['currentVersion'] ?? null);
        $updates = $probe->getUpdates();
        self::assertCount(1, $updates, json_encode($probe->getSeenSql()));
        self::assertSame('character', $updates[0]['table']);
        self::assertSame($defenderId, $updates[0]['id']);
        self::assertSame($defenderVersion, $updates[0]['expectedVersion']);
        self::assertNotEquals($defenderSheet, $updates[0]['sheet']);
        $after = $this->characterFacade()->get($defenderId);
        self::assertEquals($defenderSheet, $after->getSheet());
        self::assertEquals($defenderChoices, $after->getChoices());
        self::assertSame($defenderVersion + 1, $after->getActualVersion());
        self::assertSame($attackerVersion, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertSame($commandsBefore, $this->countAll(GameStrikeCommandTable::class));
        self::assertSame(1, (int) $mysql->select('select open from game_strike')[0]->open);
        self::assertSame(2, (int) $mysql->select('select state_version from game_battle where id = ?', [$battleId])[0]->state_version);
    }

    /**
     * Auto-fail {0|-1} не пишет penetration, урон, травму и mutation.
     *
     * @return void
     */
    public function testAutoFailBoundarySkipsPenetrationLayersInjuryAndMutation(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true, 0, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Auto-Fail', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => -1]],
        ], [], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => []],
            ['id' => 2, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => ['penetrator']],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Plate', [
            ['characteristicCode' => 'agility', 'value' => ['base' => 3, 'size' => 0]],
        ], [
            'resources' => [['ruleCode' => 'focus', 'current' => 5]],
        ], [['id' => 1, 'ruleCode' => 'sword', 'equipped' => true]]);
        $defender = $this->characterFacade()->get($defenderId);
        $defenderVersion = $defender->getActualVersion();
        $attackerVersion = $this->characterFacade()->get($attackerId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'auto-fail-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'auto-fail-open',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ])['success']);
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, []);
        try {
            $closed = $this->dispatch('game.resolveStrike', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'auto-fail-close',
                'expectedVersion' => 2,
                'expectedSheetVersion' => $defenderVersion,
                'defense' => [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'sword',
                ],
            ]);
        } finally {
            $probe->disarm();
        }

        self::assertTrue($closed['success'] ?? false, json_encode($closed));
        self::assertSame(['base' => 0, 'size' => -1], $closed['data']['attackerRoll']['roll'] ?? null);
        self::assertSame(0, $closed['data']['success']);
        self::assertSame($this->pair(0), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame($this->pair(0), $closed['data']['injury']);
        self::assertSame($defenderVersion + 1, $closed['data']['sheetVersion']);
        self::assertCount(1, $probe->getUpdates(), json_encode($probe->getSeenSql()));
        self::assertSame($defenderId, $probe->getUpdates()[0]['id']);
        $afterDefender = $this->characterFacade()->get($defenderId);
        self::assertSame($defenderVersion + 1, $afterDefender->getActualVersion());
        self::assertSame([['current' => 0, 'ruleCode' => 'focus']], $afterDefender->getSheet()['resources']);
        self::assertSame($attackerVersion, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertSame(0, (int) $mysql->select('select open from game_strike')[0]->open);
        self::assertSame(3, (int) $mysql->select('select state_version from game_battle where id = ?', [$battleId])[0]->state_version);
    }

    /**
     * Нехватки ресурса хватает для automatic ignore без penetration и mutation.
     *
     * @return void
     */
    public function testInsufficientResourceIgnoreSkipsPenetrationLayersInjuryAndMutation(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true, null, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Penetrator', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 1, 'size' => 0]],
        ], [], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => []],
            ['id' => 2, 'ruleCode' => 'sword', 'equipped' => true, 'modifiers' => ['penetrator']],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Plate', [
            ['characteristicCode' => 'agility', 'value' => ['base' => 3, 'size' => 0]],
        ], [
            'resources' => [['ruleCode' => 'focus', 'current' => 1]],
        ], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true],
            ['id' => 2, 'ruleCode' => 'plate', 'equipped' => true],
        ]);
        $defender = $this->characterFacade()->get($defenderId);
        $defenderVersion = $defender->getActualVersion();
        $defenderSheet = $defender->getSheet();
        $attackerSheet = $this->characterFacade()->get($attackerId)->getSheet();
        self::assertEquals([['ruleCode' => 'focus', 'current' => 1]], $defenderSheet['resources'] ?? null);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'resource-ignore-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'] ?? false, json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'resource-ignore-open',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, []);
        try {
            $closed = $this->dispatch('game.resolveStrike', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'resource-ignore-close',
                'expectedVersion' => 2,
                'expectedSheetVersion' => $defenderVersion,
                'defense' => [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'sword',
                ],
            ]);
        } finally {
            $probe->disarm();
        }

        self::assertTrue($closed['success'] ?? false, json_encode($closed));
        self::assertSame(0, $closed['data']['success']);
        self::assertSame(2, $closed['data']['version']);
        self::assertSame($this->pair(0), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame($this->pair(0), $closed['data']['injury']);
        self::assertNull($closed['data']['sheetVersion']);
        self::assertArrayHasKey('defenderRoll', $closed['data']);
        self::assertSame([], $probe->getUpdates(), json_encode($probe->getSeenSql()));
        self::assertEquals($defenderSheet, $this->characterFacade()->get($defenderId)->getSheet());
        self::assertSame($defenderVersion, $this->characterFacade()->get($defenderId)->getActualVersion());
        self::assertEquals($attackerSheet, $this->characterFacade()->get($attackerId)->getSheet());
        self::assertSame(1, (int) $mysql->select('select open from game_strike')[0]->open);
        self::assertSame(2, (int) $mysql->select('select state_version from game_battle where id = ?', [$battleId])[0]->state_version);
        $replay = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'resource-ignore-close',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => [
                'reaction' => 'block',
                'blockItemInventoryId' => 1,
                'blockItemProfileIndex' => 0,
                'blockItemRuleCode' => 'sword',
            ],
        ]);
        self::assertEquals($closed['data'], $replay['data']);
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
     * Мир с оружием и щитом без профиля атаки.
     *
     * @param int $hitCards Сколько карточек попадания.
     * @param bool $withPool Есть ли число кубов и граней.
     * @param int $soakCards Сколько характеристик уклонения.
     * @param bool $withPenetration Живая формула penetration.
     * @param int|null $hitEfficiency Явная эффективность hit-check.
     * @param bool $withBlockResource Дорогая block-реакция focus.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function worldWithWeapon(
        int $hitCards = 1,
        bool $withPool = true,
        int $soakCards = 0,
        bool $withPenetration = false,
        ?int $hitEfficiency = null,
        bool $withBlockResource = false,
    ): RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $reliabilityCutId = $this->reliabilityCutId($mechanics);
        $world = $this->addWorldWithRevision(
            'razrabotka-' . $hitCards . ($withPool ? '-pool' : '-empty') . '-s' . $soakCards
            . ($withPenetration ? '-penetration' : ''),
        );
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
        if ($hitEfficiency !== null) {
            $check['default_efficiency'] = $hitEfficiency;
        }
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
                'proficiency_family_code' => 'melee',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        ...($withPenetration ? [
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
                        ] : []),
                    ]],
                ],
                'block_profile' => [
                    'efficiency' => ['base' => 3, 'size' => 0],
                    'defense' => ['base' => 1, 'size' => 0],
                    'resistances' => [],
                ],
            ], [], [], 'needs_work')),
            ...($withPenetration ? [
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
            ] : []),
            RuleCommitEntry::put('axe', new RuleVersionBody('item', 'Axe', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
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
            RuleCommitEntry::put('crush', new RuleVersionBody('damage_type', 'Crush', '', [
                'forms' => ['genitive' => 'crush', 'dative' => 'crush'],
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
                        'damage_type_code' => 'crush',
                        'value' => ['base' => 2, 'size' => 0],
                        'durability' => 1,
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('plate', new RuleVersionBody('item', 'Plate', '', [
                'category' => 'armor',
                'armor' => $withPenetration ? [
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
                ] : ['strength_penalty' => 1],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('junk', new RuleVersionBody('item', 'Junk', '', [
                'category' => 'armor',
                'armor' => ['resistance_slots' => 'nope'],
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
                'proficiency_family_code' => 'melee',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'dodge_benefit' => -1,
                    ]],
                ],
            ], [], [], 'needs_work'));
            $entries[] = RuleCommitEntry::put('wand', new RuleVersionBody('item', 'Wand', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
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

        if ($withBlockResource) {
            $entries[] = RuleCommitEntry::put('focus', new RuleVersionBody('resource', 'Focus', '', [
                'is_dimensional' => false,
                'auto_add' => false,
                'check_token' => false,
            ], [], [], 'needs_work'));
            $entries[] = RuleCommitEntry::put('brace', new RuleVersionBody('ability', 'Brace', '', [
                'type' => 'action',
                'combat_action' => 'block',
                'components' => [[
                    'type' => 'resource',
                    'resource_code' => 'focus',
                    'amount' => 5,
                ]],
                'distinct_weapons' => false,
                'same_weapon' => false,
                'lift_parent_max_weapons' => false,
                'peak_concentration' => false,
                'will_focus' => false,
                'long_tension' => false,
                'multiple' => false,
            ], [], [], 'needs_work'));
        }

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
            'itemInventoryId' => 1,
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
        $gameId = $this->addGame($spaceId, $this->worldRevision());
        $attackerId = $this->admit(
            $spaceId,
            $gameId,
            'Hero-' . $itemRuleCode . '-' . $gameId,
            [],
            [],
            [['ruleCode' => $itemRuleCode, 'equipped' => true]],
        );
        $defenderId = $this->admit(
            $spaceId,
            $gameId,
            'Guard-' . $gameId,
            [],
            $sheet,
            $reaction === 'block' && $inventory === []
                ? [['ruleCode' => 'buckler', 'equipped' => true]]
                : $inventory,
        );
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
                'itemInventoryId' => 1,
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
                ? [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'buckler',
                ]
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
        $gameId = $this->addGame($spaceId, $this->worldRevision());
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
            'weapon_mastery' => ['profiles' => ['strike']],
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
    ): int {
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
            'characteristicPurchases' => $purchases,
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
            $this->storedChoices($name, $inventory === [] ? [
                ['ruleCode' => 'sword', 'equipped' => true],
            ] : $inventory),
            $document,
            2,
            1,
        );
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
     * Выполняет два public strike request в независимых worker-процессах.
     *
     * @param string $action Action.
     * @param array<string, mixed> $payload JSON payload.
     *
     * @return list<array<string, mixed>> Ответы.
     */
    private function runConcurrentStrikeRequests(string $action, array $payload): array
    {
        $workers = [];
        foreach ([0, 1] as $workerIndex) {
            $sockets = stream_socket_pair(AF_UNIX, SOCK_STREAM, 0);
            if ($sockets === false) {
                self::fail('Unable to create strike worker socket pair');
            }

            $pid = pcntl_fork();
            if ($pid === -1) {
                self::fail('Unable to fork strike worker');
            }

            if ($pid === 0) {
                fclose($sockets[0]);
                $this->runConcurrentStrikeWorker($sockets[1], $action, $payload);
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
            self::assertSame(0, pcntl_wexitstatus($status), json_encode($results[$workerIndex]));
        }

        return $results;
    }

    /**
     * Загружает приложение worker и выполняет один request.
     *
     * @param resource $socket IPC-сокет.
     * @param string $action Action.
     * @param array<string, mixed> $payload JSON payload.
     *
     * @return never
     */
    private function runConcurrentStrikeWorker($socket, string $action, array $payload): never
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
                throw new RuntimeException('Strike worker did not receive start barrier');
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
            'inventory' => $this->inventoryWithIds($inventory),
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'limits' => [],
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
        $bought[] = ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]];
        $bought[] = ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]];
        $sheet['characteristicPurchases'] = $bought;
        if (!array_key_exists('abilityLevels', $sheet)) {
            $sheet['abilityLevels'] = [];
        }
        $version['sheet'] = $sheet;
        $choices = $version['choices'] ?? [];
        if (is_array($choices) && ($choices['inventory'] ?? []) === []) {
            $choices['inventory'] = [['id' => 1, 'ruleCode' => 'sword', 'equipped' => true]];
            $version['choices'] = $choices;
        }
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
