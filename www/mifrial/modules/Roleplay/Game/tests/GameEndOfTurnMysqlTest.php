<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Service\GameStrikeSheetWrites;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameStrikeTable;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Game 32 MySQL: конец хода, кровь, DOT, долг и курсор.
 */
final class GameEndOfTurnMysqlTest extends TestCase
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
     * endTurn вне боя отказывает, лист не меняется.
     *
     * @return void
     */
    public function testEndTurnOutsideBattleLeavesSheet(): void
    {
        $world = $this->world();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $before = $this->characterFacade()->get($hero)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $refused = $this->endTurn($gameId, 1, 'outside', 1, $before);
        self::assertSame('GAME_INVALID', $refused['error']['code'] ?? null, json_encode($refused));
        self::assertSame($before, $this->characterFacade()->get($hero)->getActualVersion());
        self::assertSame([], $this->states($hero));
    }

    /**
     * Open 1→1 блокирует конец хода. Долг remaining>0 не блокирует.
     *
     * @return void
     */
    public function testOpenStrikeBlocksEndTurnButDebtDoesNot(): void
    {
        $opened = $this->battlePair();
        $declared = $this->declare($opened, 'open-hit', $this->strike($opened['hero'], $opened['ally'], 'swing'));
        self::assertTrue($declared['success'], json_encode($declared));
        self::assertArrayNotHasKey('debt', $declared['data']);
        $sheet = $this->characterFacade()->get($opened['hero'])->getActualVersion();
        $blocked = $this->endTurn($opened['gameId'], $opened['battleId'], 'blocked', $declared['data']['version'], $sheet);
        self::assertSame('GAME_INVALID', $blocked['error']['code'] ?? null, json_encode($blocked));
        self::assertSame(0, $this->battleRow($opened['battleId'])['active_index']);
        self::assertSame($sheet, $this->characterFacade()->get($opened['hero'])->getActualVersion());

        $debt = $this->battlePair();
        $strikesBefore = $this->countAll(GameStrikeTable::class);
        $held = $this->declare($debt, 'debt-hit', $this->strike($debt['hero'], $debt['ally'], 'heavy'));
        self::assertTrue($held['success'], json_encode($held));
        self::assertTrue($held['data']['debt']);
        self::assertSame($strikesBefore, $this->countAll(GameStrikeTable::class));
        $passed = $this->endTurn(
            $debt['gameId'],
            $debt['battleId'],
            'debt-pass',
            $held['data']['version'],
            $this->characterFacade()->get($debt['hero'])->getActualVersion(),
        );
        self::assertTrue($passed['success'], json_encode($passed));
        self::assertSame(1, $passed['data']['activeIndex']);
        self::assertArrayHasKey('character:' . $debt['hero'], $this->battleRow($debt['battleId'])['action_debt']);
    }

    /**
     * Маркер свёртывания пишет clotting. Без маркера поле 0, тик крови есть. Зажим поднимает закрытость.
     *
     * @return void
     */
    public function testClottingMarkerHeldClosureAndUnmarkedTick(): void
    {
        $scene = $this->battlePair();
        $version = $this->mutate($scene['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'gash',
            'value' => 4,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ], [
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 2,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ], [
            'kind' => 'putState',
            'stateRuleCode' => 'pinch',
            'value' => 3,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => ['kind' => 'character', 'id' => $scene['ally']],
        ]]);
        $ended = $this->endTurn($scene['gameId'], $scene['battleId'], 'clot', 2, $version);
        self::assertTrue($ended['success'], json_encode($ended));
        $rows = $this->rowsByCode($scene['hero']);
        self::assertGreaterThan(0, $rows['gash']['clotting']);
        self::assertLessThanOrEqual(4, $rows['gash']['clotting']);
        self::assertSame(0, $rows['nick']['clotting']);
        self::assertSame(0, $rows['pinch']['clotting']);
        self::assertSame(2, $rows['nick']['value']);
        self::assertSame(3, $rows['pinch']['value']);
        $nickTick = 2;
        $pinchTick = 2;
        $gashTick = 4 - min(4, $rows['gash']['clotting']);
        self::assertSame($nickTick + $pinchTick + $gashTick, $rows['blood']['value']);
    }

    /**
     * Кровь копится в isBloodLoss. Пол floor(кровь/10). Воля при Δ. Увечье только если ход увеличил кровь и итог ≥ 40.
     *
     * @return void
     */
    public function testBloodFloorWillAndMaimOnlyWhenRaisedPastForty(): void
    {
        $small = $this->battlePair();
        $smallVersion = $this->mutate($small['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 12,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]]);
        $smallEnd = $this->endTurn($small['gameId'], $small['battleId'], 'bleed-small', 2, $smallVersion);
        self::assertTrue($smallEnd['success'], json_encode($smallEnd));
        $smallRows = $this->rowsByCode($small['hero']);
        self::assertSame(12, $smallRows['blood']['value']);
        self::assertSame(1, $smallRows['tired']['value']);
        self::assertArrayHasKey('weak', $smallRows);
        self::assertArrayNotHasKey('maim', $smallRows);

        $past = $this->battlePair();
        $pastVersion = $this->mutate($past['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'blood',
            'value' => 39,
        ], [
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 1,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]]);
        $pastEnd = $this->endTurn($past['gameId'], $past['battleId'], 'bleed-40', 2, $pastVersion);
        self::assertTrue($pastEnd['success'], json_encode($pastEnd));
        $pastRows = $this->rowsByCode($past['hero']);
        self::assertSame(40, $pastRows['blood']['value']);
        self::assertSame(4, $pastRows['tired']['value']);
        self::assertArrayHasKey('maim', $pastRows);

        $quiet = $this->battlePair();
        $quietVersion = $this->mutate($quiet['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'blood',
            'value' => 45,
        ], [
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 2,
            'bandage' => 2,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]]);
        $quietEnd = $this->endTurn($quiet['gameId'], $quiet['battleId'], 'bleed-zero', 2, $quietVersion);
        self::assertTrue($quietEnd['success'], json_encode($quietEnd));
        $quietRows = $this->rowsByCode($quiet['hero']);
        self::assertSame(45, $quietRows['blood']['value']);
        self::assertArrayNotHasKey('maim', $quietRows);
    }

    /**
     * DOT стреляет, чужой step пропускается, сила ≤ 0 снимает строку, sidecar раны не стреляет.
     *
     * @return void
     */
    public function testDotFiresSkipsForeignStepAndRemovesSpentRow(): void
    {
        $scene = $this->battlePair();
        $version = $this->mutate($scene['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'burn',
            'value' => 1,
        ], [
            'kind' => 'putState',
            'stateRuleCode' => 'linger',
            'value' => 7,
        ], [
            'kind' => 'putState',
            'stateRuleCode' => 'gash',
            'value' => 5,
            'bandage' => 5,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]]);
        $ended = $this->endTurn($scene['gameId'], $scene['battleId'], 'dot', 2, $version);
        self::assertTrue($ended['success'], json_encode($ended));
        $rows = $this->rowsByCode($scene['hero']);
        self::assertArrayNotHasKey('burn', $rows);
        self::assertSame(7, $rows['linger']['value']);
        self::assertSame(5, $rows['gash']['value']);
        self::assertArrayNotHasKey('dotTurnsLeft', $rows['gash']);
        self::assertArrayHasKey('hurt', $rows);
        self::assertGreaterThan(0, $rows['tired']['value']);
    }

    /**
     * Повтор того же body не тикает лист и не двигает курсор.
     *
     * @return void
     */
    public function testReplayDoesNotTickAgain(): void
    {
        $scene = $this->battlePair();
        $version = $this->mutate($scene['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 6,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]]);
        $body = [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'replay',
            'expectedBattleVersion' => 2,
            'expectedSheetVersion' => $version,
        ];
        $first = $this->dispatch('game.endTurn', $body);
        self::assertTrue($first['success'], json_encode($first));
        $sheet = $this->characterFacade()->get($scene['hero'])->getActualVersion();
        $cursor = $this->battleRow($scene['battleId'])['active_index'];
        $blood = $this->rowsByCode($scene['hero'])['blood']['value'];
        $second = $this->dispatch('game.endTurn', $body);
        self::assertTrue($second['success'], json_encode($second));
        self::assertEquals($first['data'], $second['data']);
        self::assertSame($sheet, $this->characterFacade()->get($scene['hero'])->getActualVersion());
        self::assertSame($cursor, $this->battleRow($scene['battleId'])['active_index']);
        self::assertSame($blood, $this->rowsByCode($scene['hero'])['blood']['value']);
    }

    /**
     * Конфликт версии не пишет лист и курсор.
     *
     * @return void
     */
    public function testVersionConflictWritesNothing(): void
    {
        $scene = $this->battlePair();
        $version = $this->mutate($scene['hero'], [[
            'kind' => 'putState',
            'stateRuleCode' => 'nick',
            'value' => 4,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]]);
        $battleBefore = $this->battleRow($scene['battleId'])['state_version'];
        $refused = $this->endTurn($scene['gameId'], $scene['battleId'], 'stale', $battleBefore, $version - 1);
        self::assertSame('GAME_CONFLICT', $refused['error']['code'] ?? null, json_encode($refused));
        self::assertSame($version, $this->characterFacade()->get($scene['hero'])->getActualVersion());
        self::assertSame($battleBefore, $this->battleRow($scene['battleId'])['state_version']);
        self::assertSame(0, $this->battleRow($scene['battleId'])['active_index']);
        self::assertArrayNotHasKey('blood', $this->rowsByCode($scene['hero']));
    }

    /**
     * NPC идёт тем же концом хода. Чужой npc.version не пишет.
     *
     * @return void
     */
    public function testNpcEndTurnUsesNpcVersion(): void
    {
        $world = $this->world();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $created = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]);
        self::assertTrue($created['success'], json_encode($created));
        $npcId = $created['data']['npcId'];
        $choices = $created['data']['version']['choices'];
        $choices['raceCode'] = 'human';
        $choices['characteristicPurchases'] = [
            ['characteristicCode' => 'strength', 'cost' => 2],
            ['characteristicCode' => 'stamina', 'cost' => 1],
        ];
        $sheet = $created['data']['version']['sheet'];
        $sheet['characteristicPurchases'] = [
            ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]],
            ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]],
        ];
        $sheet['states'] = [[
            'stateRuleCode' => 'nick',
            'value' => 8,
            'id' => 1,
            'bandage' => 0,
            'clotting' => 0,
            'internal' => false,
            'aided' => false,
            'heldBy' => null,
        ]];
        $sheet['resources'] = [['ruleCode' => 'ap', 'current' => 1]];
        $preview = $this->dispatch('game.updateNpc', [
            'gameId' => $gameId,
            'npcId' => $npcId,
            'name' => 'Guard',
            'visibility' => $created['data']['visibility'],
            'choices' => $choices,
            'sheet' => $sheet,
            'expectedNpcActualVersion' => $created['data']['actualVersion'],
        ]);
        self::assertTrue($preview['success'], json_encode($preview));
        self::assertSame('conflicts', $preview['data']['kind'] ?? null, json_encode($preview));
        $saved = $this->dispatch('game.updateNpc', [
            'gameId' => $gameId,
            'npcId' => $npcId,
            'name' => 'Guard',
            'visibility' => $created['data']['visibility'],
            'choices' => $preview['data']['choices'],
            'sheet' => $preview['data']['sheet'],
            'expectedNpcActualVersion' => $created['data']['actualVersion'],
        ]);
        self::assertTrue($saved['success'], json_encode($saved));
        self::assertArrayNotHasKey('kind', $saved['data'] ?? [], json_encode($saved));
        $mutations = $this->gameApplication()->getLocator()->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        self::assertInstanceOf(ICharacterActualMutations::class, $mutations);
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $npcVersion = (new GameStrikeSheetWrites($mutations, new GameNpcRepository($gateway)))->write(
            $this->gameFacade()->get($gameId),
            'npc',
            $npcId,
            $this->npcRecord($npcId)->getActualVersion(),
            [[
                'kind' => 'putState',
                'stateRuleCode' => 'nick',
                'value' => 8,
                'bandage' => 0,
                'clotting' => 0,
                'internal' => false,
                'aided' => false,
                'heldBy' => null,
            ]],
        );
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'npc-battle',
            'participants' => [
                ['type' => 'npc', 'id' => $npcId],
                ['type' => 'character', 'id' => $hero],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        self::assertTrue($this->dispatch('game.rollInitiative', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'npc-roll',
            'expectedVersion' => 1,
        ])['success']);
        $stale = $this->endTurn($gameId, $battleId, 'npc-stale', 2, $npcVersion - 1);
        self::assertSame('GAME_CONFLICT', $stale['error']['code'] ?? null, json_encode($stale));
        self::assertSame($npcVersion, $this->npcRecord($npcId)->getActualVersion());
        self::assertSame(2, $this->battleRow($battleId)['state_version']);
        $ended = $this->endTurn($gameId, $battleId, 'npc-end', 2, $npcVersion);
        self::assertTrue($ended['success'], json_encode($ended));
        $npc = $this->npcRecord($npcId);
        self::assertGreaterThan($npcVersion, $npc->getActualVersion());
        $blood = null;
        foreach ($npc->getVersion()['sheet']['states'] as $row) {
            if (is_array($row) && ($row['stateRuleCode'] ?? null) === 'blood') {
                $blood = $row['value'];
            }
        }
        self::assertSame(8, $blood);
    }

    /**
     * Нехватка F1 пишет слот. Continue на курсоре открывает удар. Отказ инвентаря слот оставляет. Abort и leave снимают его.
     *
     * @return void
     */
    public function testDebtContinueInventoryAbortAndLeave(): void
    {
        $scene = $this->battlePair([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $declared = $this->declare($scene, 'short', $this->throwBody($scene['hero'], $scene['ally']));
        self::assertTrue($declared['success'], json_encode($declared));
        self::assertTrue($declared['data']['debt']);
        self::assertSame(0, $this->countAll(GameStrikeTable::class));
        self::assertSame(1, $this->inventoryQuantity($scene['hero'], 1));
        $slot = $this->battleRow($scene['battleId'])['action_debt']['character:' . $scene['hero']];
        self::assertGreaterThan(0, $slot['remaining']);
        $passed = $this->endTurn(
            $scene['gameId'],
            $scene['battleId'],
            'to-ally',
            $declared['data']['version'],
            $this->characterFacade()->get($scene['hero'])->getActualVersion(),
        );
        self::assertTrue($passed['success'], json_encode($passed));
        self::assertSame(1, $passed['data']['activeIndex']);
        $abortedAway = $this->dispatch('game.abortAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'abort-ally',
            'expectedBattleVersion' => $passed['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($scene['ally'])->getActualVersion(),
        ]);
        self::assertTrue($abortedAway['success'], json_encode($abortedAway));
        self::assertArrayHasKey('character:' . $scene['hero'], $this->battleRow($scene['battleId'])['action_debt']);
        $back = $this->endTurn(
            $scene['gameId'],
            $scene['battleId'],
            'to-hero',
            $abortedAway['data']['version'],
            $this->characterFacade()->get($scene['ally'])->getActualVersion(),
        );
        self::assertTrue($back['success'], json_encode($back));
        self::assertSame(0, $back['data']['activeIndex']);
        $this->setInventoryQuantity($scene['hero'], 0);
        $continued = $this->dispatch('game.continueAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'pay',
            'expectedBattleVersion' => $back['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($scene['hero'])->getActualVersion(),
        ]);
        self::assertTrue($continued['success'], json_encode($continued));
        self::assertFalse($continued['data']['opened']);
        self::assertSame(0, $this->countAll(GameStrikeTable::class));
        self::assertSame(0, $this->inventoryQuantity($scene['hero'], 1));
        self::assertSame(0, $this->battleRow($scene['battleId'])['action_debt']['character:' . $scene['hero']]['remaining']);

        $cleared = $this->dispatch('game.abortAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'abort-here',
            'expectedBattleVersion' => $continued['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($scene['hero'])->getActualVersion(),
        ]);
        self::assertTrue($cleared['success'], json_encode($cleared));
        self::assertArrayNotHasKey('character:' . $scene['hero'], $this->battleRow($scene['battleId'])['action_debt']);

        $paid = $this->battlePair([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $opened = $this->declare($paid, 'full-debt', $this->throwBody($paid['hero'], $paid['ally']));
        self::assertTrue($opened['data']['debt'] ?? false, json_encode($opened));
        $cycle = $this->endTurn(
            $paid['gameId'],
            $paid['battleId'],
            'cycle-out',
            $opened['data']['version'],
            $this->characterFacade()->get($paid['hero'])->getActualVersion(),
        );
        $cycle = $this->endTurn(
            $paid['gameId'],
            $paid['battleId'],
            'cycle-back',
            $cycle['data']['version'],
            $this->characterFacade()->get($paid['ally'])->getActualVersion(),
        );
        $finished = $this->dispatch('game.continueAction', [
            'gameId' => $paid['gameId'],
            'battleId' => $paid['battleId'],
            'idempotencyKey' => 'finish',
            'expectedBattleVersion' => $cycle['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($paid['hero'])->getActualVersion(),
        ]);
        self::assertTrue($finished['success'], json_encode($finished));
        self::assertTrue($finished['data']['opened']);
        self::assertSame(0, $this->inventoryQuantity($paid['hero'], 1));
        self::assertSame([], $this->battleRow($paid['battleId'])['action_debt']);

        $left = $this->battlePair([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $slotOnly = $this->declare($left, 'leave-debt', $this->throwBody($left['hero'], $left['ally']));
        self::assertTrue($slotOnly['data']['debt'] ?? false, json_encode($slotOnly));
        self::assertTrue($this->dispatch('game.leaveCharacter', [
            'gameId' => $left['gameId'],
            'characterId' => $left['hero'],
            'membershipRevision' => 2,
        ])['success']);
        self::assertArrayNotHasKey('character:' . $left['hero'], $this->battleRow($left['battleId'])['action_debt']);
    }

    /**
     * Выбывший теряет слот. Нет прежней пары — active_index 0.
     *
     * @return void
     */
    public function testRosterDropsDepartedDebtAndResetsMissingCursor(): void
    {
        $scene = $this->battlePair([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $declared = $this->declare($scene, 'roster-debt', $this->throwBody($scene['hero'], $scene['ally']));
        self::assertTrue($declared['success'], json_encode($declared));
        $dropped = $this->dispatch('game.setBattleRoster', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'drop-hero',
            'expectedVersion' => $declared['data']['version'],
            'participants' => [
                ['type' => 'character', 'id' => $scene['ally']],
            ],
        ]);
        self::assertTrue($dropped['success'], json_encode($dropped));
        $row = $this->battleRow($scene['battleId']);
        self::assertArrayNotHasKey('character:' . $scene['hero'], $row['action_debt']);
        self::assertSame(0, $row['active_index']);

        $kept = $this->battlePair([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $keptDebt = $this->declare($kept, 'kept-debt', $this->throwBody($kept['hero'], $kept['ally']));
        $moved = $this->endTurn(
            $kept['gameId'],
            $kept['battleId'],
            'ally-turn',
            $keptDebt['data']['version'],
            $this->characterFacade()->get($kept['hero'])->getActualVersion(),
        );
        self::assertSame(1, $moved['data']['activeIndex']);
        $still = $this->dispatch('game.setBattleRoster', [
            'gameId' => $kept['gameId'],
            'battleId' => $kept['battleId'],
            'idempotencyKey' => 'drop-other',
            'expectedVersion' => $moved['data']['version'],
            'participants' => [
                ['type' => 'character', 'id' => $kept['hero']],
                ['type' => 'character', 'id' => $kept['ally']],
            ],
        ]);
        self::assertTrue($still['success'], json_encode($still));
        $keptRow = $this->battleRow($kept['battleId']);
        self::assertSame(1, $keptRow['active_index']);
        self::assertArrayHasKey('character:' . $kept['hero'], $keptRow['action_debt']);
    }

    /**
     * T5-B: declare без инициативы открывает удар.
     *
     * @return void
     */
    public function testDeclareWithoutInitiativeStillOpens(): void
    {
        $world = $this->world();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero');
        $ally = $this->admit($world->getId(), $gameId, 'Ally');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'no-roll',
            'participants' => [
                ['type' => 'character', 'id' => $hero],
                ['type' => 'character', 'id' => $ally],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        self::assertNull($this->battleRow($battle['data']['battleId'])['turn_order']);
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'free-declare',
            'expectedVersion' => 1,
            'attack' => $this->strike($hero, $ally, 'swing'),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertArrayNotHasKey('debt', $opened['data']);
        self::assertSame(1, $this->countAll(GameStrikeTable::class));
    }

    /**
     * @param array<int, array<string, mixed>> $operations Операции.
     *
     * @return int Версия листа.
     */
    private function mutate(int $characterId, array $operations): int
    {
        $mutations = $this->gameApplication()->getLocator()->get(ICharacterContainer::class)->get(ICharacterActualMutations::class);
        self::assertInstanceOf(ICharacterActualMutations::class, $mutations);
        $current = $this->characterFacade()->get($characterId)->getActualVersion();

        return $mutations->apply($characterId, $current, $operations)->getActualVersion();
    }

    /**
     * @param array<int, array<string, mixed>> $inventory Инвентарь героя.
     *
     * @return array{gameId: int, battleId: int, hero: int, ally: int}
     */
    private function battlePair(array $inventory = []): array
    {
        $world = $this->world();
        $gameId = $this->addGame($world->getId());
        $hero = $this->admit($world->getId(), $gameId, 'Hero', $inventory);
        $ally = $this->admit($world->getId(), $gameId, 'Ally');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'battle-' . $hero,
            'participants' => [
                ['type' => 'character', 'id' => $hero],
                ['type' => 'character', 'id' => $ally],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $rolled = $this->dispatch('game.rollInitiative', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'roll-' . $hero,
            'expectedVersion' => 1,
        ]);
        self::assertTrue($rolled['success'], json_encode($rolled));
        self::assertSame(0, $this->battleRow($battle['data']['battleId'])['active_index']);

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'hero' => $hero,
            'ally' => $ally,
        ];
    }

    /**
     * @param array{gameId: int, battleId: int} $scene Сцена.
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Ответ.
     */
    private function declare(array $scene, string $key, array $attack): array
    {
        return $this->dispatch('game.declareStrike', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => $key,
            'expectedVersion' => $this->battleRow($scene['battleId'])['state_version'],
            'attack' => $attack,
        ]);
    }

    /**
     * @return array<string, mixed> Ответ.
     */
    private function endTurn(int $gameId, int $battleId, string $key, int $battleVersion, int $sheetVersion): array
    {
        return $this->dispatch('game.endTurn', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key,
            'expectedBattleVersion' => $battleVersion,
            'expectedSheetVersion' => $sheetVersion,
        ]);
    }

    /**
     * @return array<string, mixed> Атака.
     */
    private function strike(int $attackerId, int $defenderId, string $action): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'defender' => ['type' => 'character', 'id' => $defenderId],
            'actionRuleCode' => $action,
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
    }

    /**
     * @return array<string, mixed> Бросок.
     */
    private function throwBody(int $attackerId, int $defenderId): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'defender' => ['type' => 'character', 'id' => $defenderId],
            'actionRuleCode' => 'heavy',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'dart',
            'profileType' => 'throw',
            'profileIndex' => 0,
        ];
    }

    /**
     * @return array<string, mixed> Строка боя.
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
     * @return list<array<string, mixed>> Строки.
     */
    private function states(int $characterId): array
    {
        $states = $this->characterFacade()->get($characterId)->getSheet()['states'] ?? [];
        self::assertIsArray($states);

        return $states;
    }

    /**
     * @return array<string, array<string, mixed>> Код → строка.
     */
    private function rowsByCode(int $characterId): array
    {
        $indexed = [];
        foreach ($this->states($characterId) as $row) {
            if (is_array($row) && is_string($row['stateRuleCode'] ?? null)) {
                $indexed[$row['stateRuleCode']] = $row;
            }
        }

        return $indexed;
    }

    private function setInventoryQuantity(int $characterId, int $quantity): void
    {
        $character = $this->characterFacade()->get($characterId);
        $choices = $character->getChoices();
        foreach ($choices['inventory'] as $index => $row) {
            if (is_array($row) && ($row['id'] ?? null) === 1) {
                $choices['inventory'][$index]['quantity'] = $quantity;
            }
        }
        $this->characterFacade()->replaceMigrated(
            $characterId,
            $character->getName(),
            true,
            $choices,
            $character->getSheet(),
            $this->worldRevision(),
            $character->getActualVersion(),
        );
    }

    private function npcRecord(int $npcId): \Mifrial\Roleplay\Game\Dto\GameNpcRecord
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return (new GameNpcRepository($gateway))->getById($npcId);
    }

    private function inventoryQuantity(int $characterId, int $inventoryId): int
    {
        foreach ($this->characterFacade()->get($characterId)->getChoices()['inventory'] as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                return is_int($row['quantity'] ?? null) ? $row['quantity'] : 0;
            }
        }

        self::fail('Inventory row is missing');
    }

    /**
     * @param class-string $table Таблица.
     */
    private function countAll(string $table): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return count($gateway->open($table)->records()->getList(new \Mifrial\Core\SmartTable\Dto\ListQuery(
            null,
            ['id' => 'ASC'],
            \Mifrial\Core\SmartTable\Dto\ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ))->rows());
    }

    /**
     * @param array<int, mixed> $inventory Инвентарь.
     */
    private function admit(int $spaceId, int $gameId, string $name, array $inventory = []): int
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
        $rows = $inventory === [] ? [[
            'id' => 1,
            'ruleCode' => 'sword',
            'equipped' => true,
            'quantity' => 1,
        ]] : $inventory;
        $this->characterFacade()->replaceMigrated(
            $characterId,
            $name,
            true,
            [
                'name' => $name,
                'raceCode' => 'human',
                'abilities' => [],
                'inventory' => $rows,
                'characteristicPurchases' => [
                    ['characteristicCode' => 'strength', 'cost' => 2],
                    ['characteristicCode' => 'stamina', 'cost' => 1],
                    ['characteristicCode' => 'will', 'cost' => 0],
                ],
                'customRules' => [],
                'limits' => [],
                'money' => 0,
                'active' => true,
            ],
            [
                'abilityLevels' => [],
                'resources' => [['ruleCode' => 'ap', 'current' => 4]],
                'characteristicPurchases' => [
                    ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]],
                    ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]],
                    ['characteristicCode' => 'will', 'value' => ['base' => 0, 'size' => 0]],
                ],
            ],
            2,
            1,
        );
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    private function addGame(int $spaceId): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => $this->worldRevision(),
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]));
    }

    /**
     * @param array<int, string> $permissionKeys Ключи.
     */
    private function setActor(int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $permissionKeys, false));
    }

    /**
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }

    private function worldRevision(): int
    {
        return $this->worldRevision ?? throw new RuntimeException('World revision was not published');
    }

    private function world(): RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->mechanicId($mechanics, 'plain_roll');
        $clotId = $this->mechanicId($mechanics, 'blood_clotting');
        $world = $this->addWorldWithRevision('end-turn-' . substr(uniqid('', true), -8));
        $flags = [
            'distinct_weapons' => false,
            'same_weapon' => false,
            'lift_parent_max_weapons' => false,
            'peak_concentration' => false,
            'will_focus' => false,
            'long_tension' => false,
            'multiple' => false,
        ];
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => false,
            'concentration_token' => false,
            'willpower' => false,
            'unstable_check' => false,
            'hit_check' => false,
            'initiative' => false,
            'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
        ];
        $this->worldRevision = $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'weapon_mastery' => ['profiles' => ['strike', 'throw']],
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ], [
                    'characteristic_code' => 'stamina',
                    'mode' => 'purchased',
                    'base' => ['base' => 4, 'size' => 0],
                    'purchase' => [['cost' => 1, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strength', new RuleVersionBody('characteristic', 'Strength', '', [
                'weapon_mastery' => ['profiles' => ['strike', 'throw']],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('stamina', new RuleVersionBody('characteristic', 'Stamina', '', [
                'damage_endurance' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('will', new RuleVersionBody('characteristic', 'Will', '', [
                'willpower' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('ap', new RuleVersionBody('resource', 'AP', '', [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'turn_refill' => true,
                'limit' => ['base' => 4, 'adjustments' => []],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing', new RuleVersionBody('ability', 'Swing', '', [
                'type' => 'action',
                'components' => [[
                    'type' => 'resource',
                    'resource_code' => 'ap',
                    'amount' => 1,
                ]],
                ...$flags,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('heavy', new RuleVersionBody('ability', 'Heavy', '', [
                'type' => 'action',
                'components' => [[
                    'type' => 'resource',
                    'resource_code' => 'ap',
                    'amount' => 5,
                ]],
                ...$flags,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('sword', new RuleVersionBody('item', 'Sword', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
                'weapon' => ['weapon_profiles' => [['type' => 'strike']]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('dart', new RuleVersionBody('item', 'Dart', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'thrown',
                'weapon' => ['weapon_profiles' => [['type' => 'throw']]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('crush', new RuleVersionBody('damage_type', 'Crush', '', [
                'forms' => ['genitive' => 'crush', 'dative' => 'crush'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('hurt', new RuleVersionBody('state', 'Hurt', '', [
                'value_type' => 'dimensional',
                'aggregation' => 'sum',
                'damage_remainder' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('tired', new RuleVersionBody('state', 'Tired', '', [
                'value_type' => 'number',
                'aggregation' => 'sum',
                'damage_exhaustion' => true,
                'check_code' => 'will-check',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('weak', new RuleVersionBody('state', 'Weak', '', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
                'decline_weakness' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('maim', new RuleVersionBody('state', 'Maim', '', [
                'value_type' => 'number',
                'aggregation' => 'max',
                'maim' => true,
                'check_code' => 'maim-check',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('blood', new RuleVersionBody('state', 'Blood', '', [
                'value_type' => 'number',
                'aggregation' => 'sum',
                'blood_loss' => true,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('gash', new RuleVersionBody('state', 'Gash', '', [
                'value_type' => 'number',
                'aggregation' => 'independent',
                'effects' => [[
                    'type' => 'damage_over_time',
                    'damage' => ['kind' => 'fixed', 'amount' => 9],
                    'damage_type_code' => 'crush',
                    'periodicity' => ['value' => 1, 'step' => 'turn'],
                ]],
            ], [], [[
                'mechanic_id' => $clotId,
                'mechanic_payload' => ['check_code' => 'clot-check'],
            ]], 'needs_work')),
            RuleCommitEntry::put('nick', new RuleVersionBody('state', 'Nick', '', [
                'value_type' => 'number',
                'aggregation' => 'independent',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('pinch', new RuleVersionBody('state', 'Pinch', '', [
                'value_type' => 'number',
                'aggregation' => 'independent',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('burn', new RuleVersionBody('state', 'Burn', '', [
                'value_type' => 'number',
                'aggregation' => 'independent',
                'effects' => [[
                    'type' => 'damage_over_time',
                    'damage' => ['kind' => 'fixed', 'amount' => 4],
                    'damage_type_code' => 'crush',
                    'periodicity' => ['value' => 1, 'step' => 'turn'],
                    'decay' => ['kind' => 'fixed', 'value' => 1],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('linger', new RuleVersionBody('state', 'Linger', '', [
                'value_type' => 'number',
                'aggregation' => 'independent',
                'effects' => [[
                    'type' => 'damage_over_time',
                    'damage' => ['kind' => 'fixed', 'amount' => 3],
                    'damage_type_code' => 'crush',
                    'periodicity' => ['value' => 1, 'step' => 'day'],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
            ]], 'needs_work')),
            RuleCommitEntry::put('initiative', new RuleVersionBody('check', 'Initiative', '', [
                ...$check,
                'ordinary_root' => true,
                'initiative' => true,
            ], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
            ]], 'needs_work')),
            RuleCommitEntry::put('clot-check', new RuleVersionBody('check', 'Clot', '', $check, [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 6]],
            ]], 'needs_work')),
            RuleCommitEntry::put('will-check', new RuleVersionBody('check', 'Will', '', [
                ...$check,
                'willpower' => true,
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'tired'],
            ], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => [
                    'type' => 'roll',
                    'data' => ['diceCount' => 1, 'dieFaces' => 1],
                    'outcomes' => [['maxRating' => 5, 'stateRuleCode' => 'weak']],
                ],
            ]], 'needs_work')),
            RuleCommitEntry::put('maim-check', new RuleVersionBody('check', 'Maim', '', $check, [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
            ]], 'needs_work')),
        ])->getRevision();

        return $world;
    }

    private function mechanicId(IMechanics $mechanics, string $code): int
    {
        try {
            return $mechanics->add($code, $code, '', '1.0.0');
        } catch (MechanicInvalidException) {
            return $mechanics->getByCodeVersion($code, '1.0.0')->getId();
        }
    }
}
