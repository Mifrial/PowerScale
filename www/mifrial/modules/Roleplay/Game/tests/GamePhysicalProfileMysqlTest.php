<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Game 29 MySQL acceptance: throw/shoot spend, compatibility, replay и CAS.
 */
final class GamePhysicalProfileMysqlTest extends TestCase
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
     * Throw списывает выбранный item 1→0 и оставляет строку.
     *
     * @return void
     */
    public function testThrowConsumesSelectedItemToZeroAndKeepsRow(): void
    {
        $prepared = $this->prepareThrow();
        $opened = $this->declareStrike($prepared, 'throw-spend', $this->throwAttack(
            $prepared['attackerId'],
            $prepared['defenderId'],
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $this->assertInventoryQuantity($prepared['attackerId'], 1, 'dart', 0);
        self::assertSame(3, $this->apCurrent($prepared['attackerId']));
    }

    /**
     * Throw NPC списывает выбранный item 1→0 и оставляет строку.
     *
     * @return void
     */
    public function testThrowConsumesNpcSelectedItemToZeroAndKeepsRow(): void
    {
        $world = $this->physicalWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $defenderId = $this->admit($world->getId(), $gameId, 'Target');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->createAttackerNpc($gameId, 'Thrower', [[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->startBattle($gameId, [
            ['type' => 'npc', 'id' => $npc['npcId']],
            ['type' => 'character', 'id' => $defenderId],
        ]);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'npc-throw',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->throwAttack($npc['npcId'], $defenderId, 'npc', 'character')),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $this->assertNpcInventoryQuantity($npc['npcId'], 1, 'dart', 0);
        self::assertSame(3, $this->npcApCurrent($gameId, $npc['npcId']));
    }

    /**
     * Shoot списывает selected ammunition 1→0 и оставляет строку оружия.
     *
     * @return void
     */
    public function testShootConsumesAmmunitionToZeroAndKeepsRow(): void
    {
        $prepared = $this->prepareShoot();
        $opened = $this->declareStrike($prepared, 'shoot-spend', $this->shootAttack(
            $prepared['attackerId'],
            $prepared['defenderId'],
            2,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $this->assertInventoryQuantity($prepared['attackerId'], 1, 'bow', 1);
        $this->assertInventoryQuantity($prepared['attackerId'], 2, 'arrow', 0);
        self::assertTrue($this->characterFacade()->get($prepared['attackerId'])->getChoices()['inventory'][0]['equipped']);
    }

    /**
     * Shoot с ignore пересчитывает дальнюю трудность, а не падает на defense.
     *
     * @return void
     */
    public function testShootIgnoreReratesRangedDifficulty(): void
    {
        $prepared = $this->prepareShoot();
        $attack = $this->shootAttack($prepared['attackerId'], $prepared['defenderId'], 2);
        $attack['conditions']['distance'] = [
            'type' => 'ipari',
            'value' => ['type' => 'literal', 'value' => ['base' => 11, 'size' => 0]],
        ];
        $attack['conditions']['cover'] = ['base' => 1, 'size' => -1];
        $opened = $this->declareStrike($prepared, 'shoot-rerate', $attack);
        self::assertTrue($opened['success'], json_encode($opened));

        $card = $this->dispatch('game.get', ['id' => $prepared['gameId']]);
        $conditions = $card['data']['battle']['openStrike']['conditions'] ?? null;
        self::assertIsArray($conditions);
        self::assertSame(['base' => 1, 'size' => -1], $conditions['cover']);
        self::assertSame('ipari', $conditions['distance']['type']);

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $prepared['gameId'],
            'battleId' => $prepared['battleId'],
            'idempotencyKey' => 'shoot-rerate-close',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($prepared['defenderId'])->getActualVersion(),
            ),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertIsInt($closed['data']['success']);
        $roll = $closed['data']['strikes'][0]['roll'] ?? [];
        self::assertSame(11, $roll['ranged_hit']['distance_value']['base'] ?? null);
        self::assertSame(['base' => 1, 'size' => -1], $roll['ranged_hit']['cover_value'] ?? null);
        self::assertSame(['base' => 0, 'size' => 0], $roll['ranged_hit']['defense_value'] ?? null);
        self::assertSame(['base' => 2, 'size' => -1], $roll['ranged_hit']['base_value'] ?? null);
        self::assertSame(3, $roll['ranged_hit']['range_size'] ?? null);
        self::assertSame(['base' => 2, 'size' => 1], $roll['ranged_hit']['difficulty'] ?? null);
        $this->assertInventoryQuantity($prepared['attackerId'], 2, 'arrow', 0);
    }

    /**
     * Один набор кодов: совместимый патрон тратится, чужой и неполный — отказ.
     *
     * @return void
     */
    public function testAmmunitionRequiresOneMatchingEquippedStack(): void
    {
        $fire = $this->prepareShoot([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'fire-arrow',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $fireOpened = $this->declareStrike($fire, 'ammo-fire', $this->shootAttack(
            $fire['attackerId'],
            $fire['defenderId'],
            2,
        ));
        self::assertTrue($fireOpened['success'], json_encode($fireOpened));
        $this->assertInventoryQuantity($fire['attackerId'], 2, 'fire-arrow', 0);

        $bolt = $this->prepareShoot([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'bolt',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $rejected = $this->declareStrike($bolt, 'ammo-bolt', $this->shootAttack(
            $bolt['attackerId'],
            $bolt['defenderId'],
            2,
        ));
        self::assertSame('GAME_INVALID', $rejected['error']['code'] ?? null, json_encode($rejected));
        $this->assertInventoryQuantity($bolt['attackerId'], 2, 'bolt', 1);

        $partial = $this->prepareShoot([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'partial-arrow',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $partialRejected = $this->declareStrike($partial, 'ammo-partial', $this->shootAttack(
            $partial['attackerId'],
            $partial['defenderId'],
            2,
        ));
        self::assertSame('GAME_INVALID', $partialRejected['error']['code'] ?? null, json_encode($partialRejected));
        $this->assertInventoryQuantity($partial['attackerId'], 2, 'partial-arrow', 1);
    }

    /**
     * Несовместимый боеприпас и пустые requirements отклоняются до mutation.
     *
     * @return void
     */
    public function testIncompatibleAndEmptyRequirementsRejectBeforeMutation(): void
    {
        $incompatible = $this->prepareShoot([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'stone',
            'equipped' => false,
            'quantity' => 1,
        ]]);
        $rejected = $this->declareStrike($incompatible, 'incompatible', $this->shootAttack(
            $incompatible['attackerId'],
            $incompatible['defenderId'],
            2,
        ));
        self::assertSame('GAME_INVALID', $rejected['error']['code'] ?? null, json_encode($rejected));
        $this->assertInventoryQuantity($incompatible['attackerId'], 2, 'stone', 1);
        self::assertSame(3, $this->apCurrent($incompatible['attackerId']));

        $unequipped = $this->prepareShoot([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'arrow',
            'equipped' => false,
            'quantity' => 1,
        ]]);
        $unequippedResult = $this->declareStrike($unequipped, 'unequipped-ammo', $this->shootAttack(
            $unequipped['attackerId'],
            $unequipped['defenderId'],
            2,
        ));
        self::assertSame('GAME_INVALID', $unequippedResult['error']['code'] ?? null, json_encode($unequippedResult));
        $this->assertInventoryQuantity($unequipped['attackerId'], 2, 'arrow', 1);
        self::assertSame(3, $this->apCurrent($unequipped['attackerId']));
    }

    /**
     * Zero quantity, missing, unequipped и duplicate отклоняются до mutation.
     *
     * @return void
     */
    public function testUnavailableInventoryRejectsBeforeMutation(): void
    {
        $zero = $this->prepareThrow([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 0,
        ]]);
        $zeroRejected = $this->declareStrike($zero, 'zero', $this->throwAttack(
            $zero['attackerId'],
            $zero['defenderId'],
        ));
        self::assertSame('GAME_INVALID', $zeroRejected['error']['code'] ?? null, json_encode($zeroRejected));
        $this->assertInventoryQuantity($zero['attackerId'], 1, 'dart', 0);
        self::assertSame(3, $this->apCurrent($zero['attackerId']));

        $missing = $this->prepareThrow();
        $missingRejected = $this->declareStrike($missing, 'missing', [
            'attacker' => ['type' => 'character', 'id' => $missing['attackerId']],
            'defender' => ['type' => 'character', 'id' => $missing['defenderId']],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 99,
            'itemRuleCode' => 'dart',
            'profileType' => 'throw',
            'profileIndex' => 0,
            'conditions' => $this->conditions(),
        ]);
        self::assertSame('GAME_INVALID', $missingRejected['error']['code'] ?? null, json_encode($missingRejected));
        $this->assertInventoryQuantity($missing['attackerId'], 1, 'dart', 1);
        self::assertSame(3, $this->apCurrent($missing['attackerId']));

        $unequipped = $this->prepareThrow([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => false,
            'quantity' => 1,
        ]]);
        $unequippedRejected = $this->declareStrike($unequipped, 'unequipped', $this->throwAttack(
            $unequipped['attackerId'],
            $unequipped['defenderId'],
        ));
        self::assertSame('GAME_INVALID', $unequippedRejected['error']['code'] ?? null, json_encode($unequippedRejected));
        $this->assertInventoryQuantity($unequipped['attackerId'], 1, 'dart', 1);

        $duplicate = $this->prepareThrow([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $duplicateRejected = $this->declareStrike($duplicate, 'duplicate', $this->throwAttack(
            $duplicate['attackerId'],
            $duplicate['defenderId'],
        ));
        self::assertSame('GAME_INVALID', $duplicateRejected['error']['code'] ?? null, json_encode($duplicateRejected));
        $duplicateRows = [];
        foreach ($this->characterFacade()->get($duplicate['attackerId'])->getChoices()['inventory'] as $row) {
            if (is_array($row) && ($row['id'] ?? null) === 1 && ($row['ruleCode'] ?? null) === 'dart') {
                $duplicateRows[] = $row;
            }
        }
        self::assertCount(2, $duplicateRows);
        self::assertSame(1, $duplicateRows[0]['quantity'] ?? null);
        self::assertSame(1, $duplicateRows[1]['quantity'] ?? null);
        self::assertSame(3, $this->apCurrent($duplicate['attackerId']));

        $missingAmmo = $this->prepareShoot();
        $ammoRejected = $this->declareStrike($missingAmmo, 'missing-ammo', $this->shootAttack(
            $missingAmmo['attackerId'],
            $missingAmmo['defenderId'],
            99,
        ));
        self::assertSame('GAME_INVALID', $ammoRejected['error']['code'] ?? null, json_encode($ammoRejected));
        $this->assertInventoryQuantity($missingAmmo['attackerId'], 2, 'arrow', 1);
        self::assertSame(3, $this->apCurrent($missingAmmo['attackerId']));
    }

    /**
     * Wide throw запрещён и не тратит предмет.
     *
     * @return void
     */
    public function testWideThrowConsumesOneItemForAllTargets(): void
    {
        $prepared = $this->prepareWideThrow();
        $rejected = $this->declareWide($prepared, 'wide-throw', $this->wideThrowAttack(
            $prepared['attackerId'],
            $prepared['targetIds'],
        ));
        self::assertSame('GAME_INVALID', $rejected['error']['code'] ?? null, json_encode($rejected));
        $this->assertInventoryQuantity($prepared['attackerId'], 1, 'dart', 1);
        self::assertSame(3, $this->apCurrent($prepared['attackerId']));
    }

    /**
     * Wide shoot запрещён и не тратит боеприпас.
     *
     * @return void
     */
    public function testWideShootConsumesOneAmmunitionForAllTargets(): void
    {
        $prepared = $this->prepareWideShoot();
        $rejected = $this->declareWide($prepared, 'wide-shoot', $this->wideShootAttack(
            $prepared['attackerId'],
            $prepared['targetIds'],
            2,
        ));
        self::assertSame('GAME_INVALID', $rejected['error']['code'] ?? null, json_encode($rejected));
        $this->assertInventoryQuantity($prepared['attackerId'], 1, 'bow', 1);
        $this->assertInventoryQuantity($prepared['attackerId'], 2, 'arrow', 1);
        self::assertSame(3, $this->apCurrent($prepared['attackerId']));
    }

    /**
     * AP и item списываются одной mutation.
     *
     * @return void
     */
    public function testThrowSpendsActionPointsAndItemInOneMutation(): void
    {
        $prepared = $this->prepareThrow();
        $before = $this->characterFacade()->get($prepared['attackerId'])->getActualVersion();
        $opened = $this->declareStrike($prepared, 'ap-item', $this->throwAttack(
            $prepared['attackerId'],
            $prepared['defenderId'],
            'character',
            'character',
            'hurl',
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $after = $this->characterFacade()->get($prepared['attackerId']);
        self::assertSame($before + 1, $after->getActualVersion());
        $this->assertInventoryQuantity($prepared['attackerId'], 1, 'dart', 0);
        self::assertSame(2, $this->apCurrent($prepared['attackerId']));
    }

    /**
     * Replay того же ключа и тела возвращает stored result без повторного spend.
     *
     * @return void
     */
    public function testShootReplaySameKeyAndBodyDoesNotSpendAgain(): void
    {
        $prepared = $this->prepareShoot();
        $attack = $this->shootAttack($prepared['attackerId'], $prepared['defenderId'], 2);
        $opened = $this->declareStrike($prepared, 'replay-shoot', $attack);
        self::assertTrue($opened['success'], json_encode($opened));
        $replay = $this->declareStrike($prepared, 'replay-shoot', $attack);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertEquals($opened['data'], $replay['data']);
        $this->assertInventoryQuantity($prepared['attackerId'], 2, 'arrow', 0);
        self::assertSame(3, $this->apCurrent($prepared['attackerId']));
    }

    /**
     * Тот же ключ с другим ammunitionInventoryId даёт GAME_CONFLICT до spend.
     *
     * @return void
     */
    public function testSameKeyDifferentAmmunitionConflictsBeforeSpend(): void
    {
        $prepared = $this->prepareShoot([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'arrow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 3,
            'ruleCode' => 'fire-arrow',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $opened = $this->declareStrike($prepared, 'conflict-ammo', $this->shootAttack(
            $prepared['attackerId'],
            $prepared['defenderId'],
            2,
        ));
        self::assertTrue($opened['success'], json_encode($opened));
        $conflict = $this->declareStrike($prepared, 'conflict-ammo', $this->shootAttack(
            $prepared['attackerId'],
            $prepared['defenderId'],
            3,
        ));
        self::assertSame('GAME_CONFLICT', $conflict['error']['code'] ?? null, json_encode($conflict));
        $this->assertInventoryQuantity($prepared['attackerId'], 2, 'arrow', 0);
        $this->assertInventoryQuantity($prepared['attackerId'], 3, 'fire-arrow', 1);
        self::assertSame(3, $this->apCurrent($prepared['attackerId']));
    }

    /**
     * Stale CAS при declaration откатывает AP и item.
     *
     * @return void
     */
    public function testThrowStaleCasRollsBackActionPointsAndItem(): void
    {
        $prepared = $this->prepareThrow();
        $before = $this->characterFacade()->get($prepared['attackerId']);
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, [$prepared['attackerId']]);
        try {
            $opened = $this->declareStrike($prepared, 'cas-throw', $this->throwAttack(
                $prepared['attackerId'],
                $prepared['defenderId'],
            ));
        } finally {
            $probe->disarm();
        }

        self::assertSame('GAME_CONFLICT', $opened['error']['code'] ?? null, json_encode($opened));
        $after = $this->characterFacade()->get($prepared['attackerId']);
        self::assertSame($before->getActualVersion() + 1, $after->getActualVersion());
        self::assertEquals($before->getChoices()['inventory'], $after->getChoices()['inventory']);
        self::assertSame(3, $this->apCurrent($prepared['attackerId']));
        $this->assertInventoryQuantity($prepared['attackerId'], 1, 'dart', 1);
    }

    /**
     * Strike transport по-прежнему отвергает ammunitionInventoryId.
     *
     * @return void
     */
    public function testStrikeRejectsAmmunitionInventoryId(): void
    {
        $world = $this->physicalWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero', [], [
            'resources' => [['ruleCode' => 'ap', 'current' => 3]],
        ], [[
            'id' => 1,
            'ruleCode' => 'sword',
            'equipped' => true,
            'quantity' => 1,
        ]]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->startBattle($gameId, [
            ['type' => 'character', 'id' => $attackerId],
            ['type' => 'character', 'id' => $defenderId],
        ]);
        $rejected = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'strike-ammo',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
                'ammunitionInventoryId' => 2,
            ]),
        ]);
        self::assertSame('INVALID_PARAMS', $rejected['error']['code'] ?? null, json_encode($rejected));
        $this->assertInventoryQuantity($attackerId, 1, 'sword', 1);
        self::assertSame(3, $this->apCurrent($attackerId));
    }

    /**
     * Мир с throw/shoot предметами, AP и keyword compatibility.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function physicalWorld(): RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $keywords = $this->gameApplication()->getLocator()->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        $rollId = $this->plainRollId($mechanics);
        $itemKw = $this->keywordId($keywords, 'item', 'Item');
        $projectileKw = $this->keywordId($keywords, 'projectile', 'Projectile');
        $arrowKw = $this->keywordId($keywords, 'arrow', 'Arrow');
        $boltKw = $this->keywordId($keywords, 'bolt', 'Bolt');
        $fireKw = $this->keywordId($keywords, 'fire-explosion', 'Fire');
        $world = $this->addWorldWithRevision('physical-profiles-' . substr(uniqid('', true), -8));
        $actionFlags = [
            'zones' => ['or' => ['kind' => 'automatic']],
            'distinct_weapons' => false,
            'same_weapon' => false,
            'lift_parent_max_weapons' => false,
            'peak_concentration' => false,
            'will_focus' => false,
            'long_tension' => false,
            'multiple' => false,
        ];
        $this->worldRevision = $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'weapon_mastery' => ['profiles' => ['strike', 'throw', 'shoot']],
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing', new RuleVersionBody('ability', 'Swing', '', [
                'type' => 'action',
                'action_components' => [],
                ...$actionFlags,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('wide-swing', new RuleVersionBody('ability', 'Wide swing', '', [
                'type' => 'action',
                'action_components' => [],
                'attack_mode' => 'wide',
                'max_targets' => 2,
                ...$actionFlags,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('hurl', new RuleVersionBody('ability', 'Hurl', '', [
                'type' => 'action',
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'ap',
                    'amount' => 1,
                ]],
                ...$actionFlags,
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('ap', new RuleVersionBody('resource', 'AP', '', [
                'is_dimensional' => false,
                'auto_add' => false,
                'check_token' => false,
                'limit' => ['base' => 3, 'adjustments' => []],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('sword', new RuleVersionBody('item', 'Sword', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
                'weapon' => ['weapon_profiles' => [['type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]]]]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('dart', new RuleVersionBody('item', 'Dart', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'thrown',
                'weapon' => ['weapon_profiles' => [['type' => 'throw', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => null], 'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]]]]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('bow', new RuleVersionBody('item', 'Bow', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'ranged',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'shoot',
                        'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 20]]],
                        'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
                        'ammunition' => ['mode' => 'item', 'keyword_codes' => ['item', 'projectile', 'arrow']],
                    ]],
                ],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('bow-empty', new RuleVersionBody('item', 'Empty bow', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'ranged',
                'weapon' => ['weapon_profiles' => [[
                    'type' => 'shoot',
                    'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 20]]],
                    'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
                    'ammunition' => ['mode' => 'item', 'keyword_codes' => ['item', 'projectile', 'arrow']],
                ]]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('arrow', new RuleVersionBody('item', 'Arrow', '', [
                'category' => 'item',
            ], [$itemKw, $projectileKw, $arrowKw], [], 'needs_work')),
            RuleCommitEntry::put('fire-arrow', new RuleVersionBody('item', 'Fire arrow', '', [
                'category' => 'item',
            ], [$itemKw, $projectileKw, $arrowKw, $fireKw], [], 'needs_work')),
            RuleCommitEntry::put('bolt', new RuleVersionBody('item', 'Bolt', '', [
                'category' => 'item',
            ], [$itemKw, $boltKw], [], 'needs_work')),
            RuleCommitEntry::put('partial-arrow', new RuleVersionBody('item', 'Partial', '', [
                'category' => 'item',
            ], [$itemKw, $arrowKw], [], 'needs_work')),
            RuleCommitEntry::put('stone', new RuleVersionBody('item', 'Stone', '', [
                'category' => 'item',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strength', new RuleVersionBody('characteristic', 'Strength', '', [
                'weapon_mastery' => ['profiles' => ['strike', 'throw', 'shoot']],
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
            RuleCommitEntry::put('maim', new RuleVersionBody('state', 'Maim', '', [
                'value_type' => 'number',
                'aggregation' => 'max',
                'maim' => true,
                'check_code' => 'maim-check',
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
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
            ]], 'needs_work')),
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
        ])->getRevision();

        return $world;
    }

    /**
     * Id каталога plain_roll.
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
     * Id признака; повтор в том же прогоне берёт лежащую строку.
     *
     * @param IKeywords $keywords Каталог.
     * @param string $code Код.
     * @param string $name Подпись.
     *
     * @return int Id.
     */
    private function keywordId(IKeywords $keywords, string $code, string $name): int
    {
        try {
            return $keywords->add($code, $name);
        } catch (KeywordInvalidException) {
            return $keywords->getByCode($code)->getId();
        }
    }

    /**
     * Фактическая ревизия мира.
     *
     * @return int Номер.
     */
    private function worldRevision(): int
    {
        return $this->worldRevision ?? throw new RuntimeException('World revision was not published');
    }

    /**
     * Черновик игры.
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
     * Допущенный персонаж.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<int, array<string, mixed>> $purchases Закупки.
     * @param array<string, mixed> $sheet Дополнение листа.
     * @param array<int, mixed> $inventory Инвентарь.
     *
     * @return int Персонаж.
     */
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
                ['ruleCode' => 'sword', 'equipped' => true, 'quantity' => 1],
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
     * Выборы листа.
     *
     * @param string $name Имя.
     * @param array<int, mixed> $inventory Строки.
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
     * Стабильные id fixture-строк.
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
     * Throw 1→1 сценарий.
     *
     * @param array<int, mixed> $inventory Инвентарь атакующего.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, defenderId: int} Сцена.
     */
    private function prepareThrow(array $inventory = []): array
    {
        return $this->prepareSingle(
            $inventory === [] ? [[
                'id' => 1,
                'ruleCode' => 'dart',
                'equipped' => true,
                'quantity' => 1,
            ]] : $inventory,
        );
    }

    /**
     * Shoot 1→1 сценарий.
     *
     * @param array<int, mixed> $inventory Инвентарь атакующего.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, defenderId: int} Сцена.
     */
    private function prepareShoot(array $inventory = []): array
    {
        return $this->prepareSingle(
            $inventory === [] ? [[
                'id' => 1,
                'ruleCode' => 'bow',
                'equipped' => true,
                'quantity' => 1,
            ], [
                'id' => 2,
                'ruleCode' => 'arrow',
                'equipped' => true,
                'quantity' => 1,
            ]] : $inventory,
        );
    }

    /**
     * Single бой персонаж против персонажа.
     *
     * @param array<int, mixed> $inventory Инвентарь атакующего.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, defenderId: int} Сцена.
     */
    private function prepareSingle(array $inventory): array
    {
        $world = $this->physicalWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero-' . $gameId, [], [
            'resources' => [['ruleCode' => 'ap', 'current' => 3]],
        ], $inventory);
        $defenderId = $this->admit($world->getId(), $gameId, 'Guard-' . $gameId);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->startBattle($gameId, [
            ['type' => 'character', 'id' => $attackerId],
            ['type' => 'character', 'id' => $defenderId],
        ]);

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'attackerId' => $attackerId,
            'defenderId' => $defenderId,
        ];
    }

    /**
     * Wide throw сценарий.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, targetIds: list<int>} Сцена.
     */
    private function prepareWideThrow(): array
    {
        return $this->prepareWide([[
            'id' => 1,
            'ruleCode' => 'dart',
            'equipped' => true,
            'quantity' => 1,
        ]]);
    }

    /**
     * Wide shoot сценарий.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, targetIds: list<int>} Сцена.
     */
    private function prepareWideShoot(): array
    {
        return $this->prepareWide([[
            'id' => 1,
            'ruleCode' => 'bow',
            'equipped' => true,
            'quantity' => 1,
        ], [
            'id' => 2,
            'ruleCode' => 'arrow',
            'equipped' => true,
            'quantity' => 1,
        ]]);
    }

    /**
     * Wide бой на две цели.
     *
     * @param array<int, mixed> $inventory Инвентарь атакующего.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, targetIds: list<int>} Сцена.
     */
    private function prepareWide(array $inventory): array
    {
        $world = $this->physicalWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero-' . $gameId, [], [
            'resources' => [['ruleCode' => 'ap', 'current' => 3]],
        ], $inventory);
        $firstId = $this->admit($world->getId(), $gameId, 'One-' . $gameId);
        $secondId = $this->admit($world->getId(), $gameId, 'Two-' . $gameId);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->startBattle($gameId, [
            ['type' => 'character', 'id' => $attackerId],
            ['type' => 'character', 'id' => $firstId],
            ['type' => 'character', 'id' => $secondId],
        ]);

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'attackerId' => $attackerId,
            'targetIds' => [$firstId, $secondId],
        ];
    }

    /**
     * NPC-атакующий с инвентарём и AP.
     *
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<int, mixed> $inventory Инвентарь.
     *
     * @return array{npcId: int, actualVersion: int} NPC.
     */
    private function createAttackerNpc(int $gameId, string $name, array $inventory): array
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
        $choices = $version['choices'] ?? null;
        self::assertIsArray($sheet);
        self::assertIsArray($choices);
        $sheet['characteristicPurchases'] = [
            ['characteristicCode' => 'strength', 'value' => ['base' => 3, 'size' => 0]],
            ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]],
        ];
        $sheet['abilityLevels'] = [];
        $sheet['resources'] = [['ruleCode' => 'ap', 'current' => 3]];
        $choices['raceCode'] = 'human';
        $choices['inventory'] = $inventory;
        $version['sheet'] = $sheet;
        $version['choices'] = $choices;

        return [
            'npcId' => $npcId,
            'actualVersion' => $repo->replaceVersion($npcId, $version, $record->getActualVersion())->getActualVersion(),
        ];
    }

    /**
     * Старт боя.
     *
     * @param int $gameId Игра.
     * @param list<array{type: string, id: int}> $participants Участники.
     *
     * @return array<string, mixed> Ответ.
     */
    private function startBattle(int $gameId, array $participants): array
    {
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-' . $gameId,
            'participants' => $participants,
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        return $battle;
    }

    /**
     * Объявляет single strike.
     *
     * @param array{gameId: int, battleId: int} $prepared Сцена.
     * @param string $key Ключ.
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Ответ.
     */
    private function declareStrike(array $prepared, string $key, array $attack): array
    {
        return $this->dispatch('game.runAction', [
            'gameId' => $prepared['gameId'],
            'battleId' => $prepared['battleId'],
            'idempotencyKey' => $key,
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($attack),
        ]);
    }

    /**
     * Объявляет wide strike.
     *
     * @param array{gameId: int, battleId: int} $prepared Сцена.
     * @param string $key Ключ.
     * @param array<string, mixed> $attack Тело.
     *
     * @return array<string, mixed> Ответ.
     */
    private function declareWide(array $prepared, string $key, array $attack): array
    {
        return $this->dispatch('game.runAction', [
            'gameId' => $prepared['gameId'],
            'battleId' => $prepared['battleId'],
            'idempotencyKey' => $key,
            'expectedVersion' => 1,
            'action' => $this->wideStrikeEnvelope($attack),
        ]);
    }

    /**
     * Тело throw.
     *
     * @param int $attackerId Атакующий.
     * @param int $defenderId Цель.
     * @param string $attackerType Вид атакующего.
     * @param string $defenderType Вид цели.
     *
     * @return array<string, mixed> Тело.
     */
    private function throwAttack(
        int $attackerId,
        int $defenderId,
        string $attackerType = 'character',
        string $defenderType = 'character',
        string $actionRuleCode = 'swing',
    ): array {
        return [
            'attacker' => ['type' => $attackerType, 'id' => $attackerId],
            'defender' => ['type' => $defenderType, 'id' => $defenderId],
            'actionRuleCode' => $actionRuleCode,
            'itemInventoryId' => 1,
            'itemRuleCode' => 'dart',
            'profileType' => 'throw',
            'profileIndex' => 0,
            'conditions' => $this->conditions(),
        ];
    }

    /**
     * Тело shoot.
     *
     * @param int $attackerId Атакующий.
     * @param int $defenderId Цель.
     * @param int $ammunitionInventoryId Строка боеприпаса.
     *
     * @return array<string, mixed> Тело.
     */
    private function shootAttack(int $attackerId, int $defenderId, int $ammunitionInventoryId): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'defender' => ['type' => 'character', 'id' => $defenderId],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'bow',
            'profileType' => 'shoot',
            'profileIndex' => 0,
            'ammunitionInventoryId' => $ammunitionInventoryId,
            'conditions' => $this->conditions(),
        ];
    }

    /**
     * Wide throw.
     *
     * @param int $attackerId Атакующий.
     * @param list<int> $targetIds Цели.
     *
     * @return array<string, mixed> Тело.
     */
    private function wideThrowAttack(int $attackerId, array $targetIds): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'targets' => $this->characterTargets($targetIds),
            'actionRuleCode' => 'wide-swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'dart',
            'profileType' => 'throw',
            'profileIndex' => 0,
            'conditions' => $this->conditions(),
        ];
    }

    /**
     * Wide shoot.
     *
     * @param int $attackerId Атакующий.
     * @param list<int> $targetIds Цели.
     * @param int $ammunitionInventoryId Боеприпас.
     *
     * @return array<string, mixed> Тело.
     */
    private function wideShootAttack(int $attackerId, array $targetIds, int $ammunitionInventoryId): array
    {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'targets' => $this->characterTargets($targetIds),
            'actionRuleCode' => 'wide-swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'bow',
            'profileType' => 'shoot',
            'profileIndex' => 0,
            'ammunitionInventoryId' => $ammunitionInventoryId,
            'conditions' => $this->conditions(),
        ];
    }

    /**
     * Цели-персонажи.
     *
     * @param list<int> $targetIds Id.
     *
     * @return list<array{type: string, id: int}> Цели.
     */
    private function characterTargets(array $targetIds): array
    {
        $targets = [];
        foreach ($targetIds as $targetId) {
            $targets[] = ['type' => 'character', 'id' => $targetId];
        }

        return $targets;
    }

    /**
     * Условия дальнего боя для тестов: дистанция 0, без укрытия.
     *
     * @return array<string, mixed> Условия.
     */
    private function conditions(): array
    {
        return [
            'distance' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
            'cover' => ['base' => 0, 'size' => 0],
            'flank' => false,
        ];
    }

    /**
     * Проверяет quantity выбранной строки.
     *
     * @param int $characterId Персонаж.
     * @param int $inventoryId Строка.
     * @param string $ruleCode Код.
     * @param int $quantity Остаток.
     *
     * @return void
     */
    private function assertInventoryQuantity(
        int $characterId,
        int $inventoryId,
        string $ruleCode,
        int $quantity,
    ): void {
        $matches = [];
        foreach ($this->characterFacade()->get($characterId)->getChoices()['inventory'] as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matches[] = $row;
            }
        }
        self::assertCount(1, $matches);
        self::assertSame($ruleCode, $matches[0]['ruleCode'] ?? null);
        self::assertSame($quantity, $matches[0]['quantity'] ?? null);
    }

    /**
     * Quantity NPC.
     *
     * @param int $npcId NPC.
     * @param int $inventoryId Строка.
     * @param string $ruleCode Код.
     * @param int $quantity Остаток.
     *
     * @return void
     */
    private function assertNpcInventoryQuantity(
        int $npcId,
        int $inventoryId,
        string $ruleCode,
        int $quantity,
    ): void {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $choices = (new GameNpcRepository($gateway))->getById($npcId)->getVersion()['choices'] ?? null;
        self::assertIsArray($choices);
        $matches = [];
        foreach ($choices['inventory'] as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $inventoryId) {
                $matches[] = $row;
            }
        }
        self::assertCount(1, $matches);
        self::assertSame($ruleCode, $matches[0]['ruleCode'] ?? null);
        self::assertSame($quantity, $matches[0]['quantity'] ?? null);
    }

    /**
     * Текущие AP персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return int Остаток.
     */
    private function apCurrent(int $characterId): int
    {
        foreach ($this->characterFacade()->get($characterId)->getSheet()['resources'] as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === 'ap') {
                self::assertIsInt($row['current'] ?? null);

                return $row['current'];
            }
        }

        self::fail('AP resource is missing');
    }

    /**
     * Текущие AP NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int Остаток.
     */
    private function npcApCurrent(int $gameId, int $npcId): int
    {
        $sheet = $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npcId,
        ])['data']['version']['sheet'];
        foreach ($sheet['resources'] as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === 'ap') {
                self::assertIsInt($row['current'] ?? null);

                return $row['current'];
            }
        }

        self::fail('NPC AP resource is missing');
    }
}
