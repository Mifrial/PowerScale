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
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
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
     * Атака пишет выбор, защита закрывает удар и пишет лист с версией.
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
        self::assertSame(1, $this->countActions('strike'));
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
        self::assertSame(8, $closed['data']['success']);
        self::assertSame($this->pair(6), $closed['data']['damage']);
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
        self::assertSame(1, $this->countActions('strike'));
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
     * Висящие эффекты атакующего переживают ответ цели с реакцией.
     *
     * @return void
     */
    public function testResolveKeepsAttackerPendingEffectsWithReaction(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Quick', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Dodger');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-pending',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        self::assertSame(10, $this->actionPointsOf($attackerId));
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-pending-1',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(8, $this->actionPointsOf($attackerId));
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-pending-1',
            'expectedVersion' => $opened['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'dodge'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(
            [
                'after_action_until_resource_spent_check_modifier',
                'next_action_attack_cost',
                'last_strike_snapshot',
            ],
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
        $second = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-pending-2',
            'expectedVersion' => $closed['data']['version'],
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        self::assertSame(5, $this->actionPointsOf($attackerId));
        self::assertSame(
            ['after_action_until_resource_spent_check_modifier', 'last_strike_snapshot'],
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
        $closedAgain = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-pending-2',
            'expectedVersion' => $second['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closedAgain['success'], json_encode($closedAgain));
        self::assertSame(
            [
                'after_action_until_resource_spent_check_modifier',
                'next_action_attack_cost',
                'last_strike_snapshot',
            ],
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
    }

    /**
     * Ремап карточки подменяет грани до правила 6 и 1.
     *
     * Мир задаёт одну грань (dieFaces 1), поэтому результат детерминирован.
     *
     * @return void
     */
    public function testCardFaceRemapChangesAdjustedRolls(): void
    {
        $world = $this->worldWithWeapon(cardEffects: [$this->remapEffect(1, 6)]);
        $roll = $this->strikeRollAfterCard($world);
        $faces = $roll['faces'];
        self::assertNotSame([], $faces);

        self::assertSame(array_fill(0, count($faces), 1), $faces);
        self::assertSame(array_fill(0, count($faces), 6), $roll['adjustedRolls']);
        self::assertSame(array_fill(0, count($faces), 0), $roll['successes']);
    }

    /**
     * Дельта успехов карточки добавляет успех каждой грани.
     *
     * Слоение с правилом 6 и 1 проверяется на Mechanic-уровне: в этом мире
     * карточка попадания его не подключает.
     *
     * @return void
     */
    public function testCardScoreDeltaAddsSuccessToEachFace(): void
    {
        $world = $this->worldWithWeapon(cardEffects: [$this->scoreEffect(1, -1)]);
        $roll = $this->strikeRollAfterCard($world);
        $faces = $roll['faces'];
        self::assertNotSame([], $faces);

        self::assertSame(array_fill(0, count($faces), 1), $roll['adjustedRolls']);
        self::assertSame(array_fill(0, count($faces), 2), $roll['successes']);
        self::assertSame(2 * count($faces), $roll['roll']['base']);
    }

    /**
     * Хвост карточки применяется к следующей атаке по той же цели и снимается.
     *
     * @return void
     */
    public function testNextAttackScoreTailAppliesToNextStrike(): void
    {
        $world = $this->worldWithWeapon(cardEffects: [[
            'type' => 'next_action_attack_score_adjust',
            'oneDelta' => -1,
            'faceDelta' => 0,
            'same_target' => true,
        ]]);
        $result = $this->twoStrikesAfterCard($world);
        $rolls = $result['rolls'];
        self::assertCount(2, $rolls);

        $pending = $result['pending'];
        self::assertCount(2, $pending);
        $effect = is_array($pending[0]) ? ($pending[0]['effect'] ?? null) : null;
        self::assertIsArray($effect);
        self::assertSame('next_action_attack_score_adjust', $effect['type']);
        self::assertSame('character:' . $result['defenderId'], $effect['targetKey']);

        $snapshot = is_array($pending[1]) ? ($pending[1]['effect'] ?? null) : null;
        self::assertIsArray($snapshot);
        self::assertSame('last_strike_snapshot', $snapshot['type']);
        self::assertSame('other', $snapshot['kind']);
        self::assertSame('character:' . $result['defenderId'], $snapshot['hits'][0]['targetKey']);

        $first = $rolls[0]['successes'];
        $second = $rolls[1]['successes'];
        self::assertSame(array_fill(0, count($first), 1), $first);
        self::assertSame(array_fill(0, count($second), 0), $second);
    }

    /**
     * Добивание требует повреждения прошлого удара и той же цели.
     *
     * @return void
     */
    public function testFollowUpGateRequiresDamagingPreviousAttack(): void
    {
        $world = $this->worldWithWeapon(withPenetration: true, extraCards: [
            'critical' => [[
                'type' => 'require_previous_attack',
                'all_damaged' => true,
                'same_target' => true,
                'single_strike' => true,
            ]],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender');
        $otherId = $this->admit($world->getId(), $gameId, 'Other');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-follow-up',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $otherId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];

        $blocked = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-blocked',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId, 'critical'),
        ]);
        self::assertFalse($blocked['success']);
        self::assertSame('GAME_INVALID', $blocked['error']['code']);

        $first = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-first',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-follow-up-first',
            'expectedVersion' => $first['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $pending = $this->pendingEntries($battleId, 'character:' . $attackerId);
        $snapshot = null;
        foreach ($pending as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && ($effect['type'] ?? null) === 'last_strike_snapshot') {
                $snapshot = $effect;
            }
        }

        self::assertIsArray($snapshot);
        self::assertSame('other', $snapshot['kind']);
        self::assertTrue($snapshot['hits'][0]['damaged']);

        $wrongTarget = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-wrong',
            'expectedVersion' => $closed['data']['version'],
            'attack' => $this->characterAttack($attackerId, $otherId, 'critical'),
        ]);
        self::assertFalse($wrongTarget['success']);
        self::assertSame('GAME_INVALID', $wrongTarget['error']['code']);

        $second = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-second',
            'expectedVersion' => $closed['data']['version'],
            'attack' => $this->characterAttack($attackerId, $defenderId, 'critical'),
        ]);
        self::assertTrue($second['success'], json_encode($second));
    }

    /**
     * Смертельный удар добавляет успех от прошлого удара и пишет lethal-снимок.
     *
     * @return void
     */
    public function testLethalStrikeBoostsSuccessFromPrevious(): void
    {
        $world = $this->worldWithWeapon(withPenetration: true, extraCards: [
            'lethal' => [
                [
                    'type' => 'require_previous_strike',
                    'min_sr' => 1,
                    'not_kind' => 'lethal',
                    'same_target' => true,
                ],
                [
                    'type' => 'attack_sr_from_previous',
                    'floor_div' => 1,
                    'cap' => 'double_this',
                ],
            ],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-lethal',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $first = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lethal-first',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-lethal-first',
            'expectedVersion' => $first['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $previousSr = $closed['data']['success'];
        self::assertGreaterThan(0, $previousSr);

        $second = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lethal-second',
            'expectedVersion' => $closed['data']['version'],
            'attack' => $this->characterAttack($attackerId, $defenderId, 'lethal'),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        $closedLethal = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-lethal-second',
            'expectedVersion' => $second['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closedLethal['success'], json_encode($closedLethal));
        self::assertSame($previousSr * 2, $closedLethal['data']['success']);

        $pending = $this->pendingEntries($battleId, 'character:' . $attackerId);
        $snapshot = null;
        foreach ($pending as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && ($effect['type'] ?? null) === 'last_strike_snapshot') {
                $snapshot = $effect;
            }
        }

        self::assertIsArray($snapshot);
        self::assertSame('lethal', $snapshot['kind']);
        self::assertSame($previousSr * 2, $snapshot['hits'][0]['attackSr']);
    }

    /**
     * Противодействие после удара пишет подготовку и снимает снимок.
     *
     * @return void
     */
    public function testPreparedDefenseCounterAfterSingleStrike(): void
    {
        $world = $this->worldWithWeapon(withPenetration: true, withPendingEffects: true, extraCards: [
            'counter' => [
                'components' => [['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 1]],
                'effects' => [[
                    'type' => 'require_previous_attack',
                    'same_target' => true,
                    'all_damaged' => false,
                    'single_strike' => true,
                ]],
            ],
            'wait' => [
                'combat_action' => 'wait',
                'components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => ['type' => 'chosen', 'max' => 'available'],
                ]],
                'effects' => [],
            ],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-action',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-action',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-action',
            'expectedVersion' => $opened['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $beforeAp = $this->actionPointsOf($attackerId);
        $sheetBefore = $this->characterFacade()->get($attackerId)->getActualVersion();
        $action = $this->dispatch('game.declareAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action',
            'expectedVersion' => $closed['data']['version'],
            'expectedSheetVersion' => $sheetBefore,
            'actor' => ['type' => 'character', 'id' => $attackerId],
            'actionRuleCode' => 'counter',
            'followUpTargetKey' => 'character:' . $defenderId,
        ]);
        self::assertTrue($action['success'], json_encode($action));
        self::assertSame($beforeAp - 1, $this->actionPointsOf($attackerId));
        $pending = $this->pendingEntries($battleId, 'character:' . $attackerId);
        $types = [];
        foreach ($pending as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && is_string($effect['type'] ?? null)) {
                $types[] = $effect['type'];
            }
        }

        self::assertContains('prepared_defense_counter', $types);
        self::assertNotContains('last_strike_snapshot', $types);
        self::assertSame($closed['data']['version'] + 1, $action['data']['version']);

        $replay = $this->dispatch('game.declareAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action',
            'expectedVersion' => $closed['data']['version'],
            'expectedSheetVersion' => $sheetBefore,
            'actor' => ['type' => 'character', 'id' => $attackerId],
            'actionRuleCode' => 'counter',
            'followUpTargetKey' => 'character:' . $defenderId,
        ]);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertSame($beforeAp - 1, $this->actionPointsOf($attackerId));
        $missing = $this->dispatch('game.declareAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action-missing',
            'expectedVersion' => $action['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
            'actor' => ['type' => 'character', 'id' => $attackerId],
            'actionRuleCode' => 'counter',
            'followUpTargetKey' => 'character:' . $defenderId,
        ]);
        self::assertFalse($missing['success']);
        self::assertSame('GAME_INVALID', $missing['error']['code'] ?? null);

        $apBeforeWait = $this->actionPointsOf($attackerId);
        self::assertSame($beforeAp - 1, $apBeforeWait);
        $wait = $this->dispatch('game.declareAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action-wait',
            'expectedVersion' => $action['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
            'actor' => ['type' => 'character', 'id' => $attackerId],
            'actionRuleCode' => 'wait',
            'followUpTargetKey' => null,
            'chosenAmounts' => ['action-points' => 3],
        ]);
        self::assertTrue($wait['success'], json_encode($wait));
        self::assertSame(3, $wait['data']['spent']);
        self::assertSame($apBeforeWait - 3, $this->actionPointsOf($attackerId));
        self::assertNull($wait['data']['targetKey']);
        self::assertSame(0, $this->battleRow($battleId)['active_index']);
        $pendingAfterWait = $this->pendingEntries($battleId, 'character:' . $attackerId);
        $typesAfterWait = [];
        foreach ($pendingAfterWait as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && is_string($effect['type'] ?? null)) {
                $typesAfterWait[] = $effect['type'];
            }
        }

        self::assertContains('prepared_defense_counter', $typesAfterWait);
        self::assertNotContains('last_strike_snapshot', $typesAfterWait);

        $turnMessages = $this->messagesByThreadKind($gameId, 'turn');
        $actionMessages = array_values(array_filter(
            $turnMessages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'совершает действие'),
        ));
        self::assertCount(2, $actionMessages);
        foreach ($actionMessages as $message) {
            self::assertSame('b' . $battleId . '-r1-t0', $this->threadOf($message)['id'] ?? null);
            $speaker = $this->attachmentsOfType([$message], 'chat_speaker')[0] ?? null;
            self::assertIsArray($speaker);
            self::assertSame('character', $speaker['kind'] ?? null);
        }
    }

    /**
     * Чат одиночного удара: порядок, вложения и ключи доставки.
     *
     * @return void
     */
    public function testSingleStrikeChatFormIsStable(): void
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
            'idempotencyKey' => 'chat-single-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'chat-single-open',
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
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'chat-single-close',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertLessThan(0, $closed['data']['maim']['rating']);

        $messages = $this->chatMessages($gameId);
        self::assertGreaterThanOrEqual(4, count($messages));
        self::assertStringContainsString('совершает действие', $messages[0]->getContent());
        self::assertStringContainsString('наносит', $messages[1]->getContent());
        self::assertSame([], $this->contentAttachments($messages[0]));
        self::assertSame([], $this->contentAttachments($messages[1]));
        self::assertSame('roll', $this->contentAttachments($messages[2])[0]['type']);
        self::assertSame('attack-calc', $this->contentAttachments($messages[3])[0]['type']);

        $details = $this->attachmentsOfType($messages, 'injury-details');
        self::assertCount(1, $details);
        self::assertArrayHasKey('injury', $details[0]);
        self::assertArrayHasKey('damageTypeName', $details[0]);
        self::assertLessThan(0, $details[0]['injury']['rating']);
        self::assertSame(-$details[0]['injury']['rating'], $details[0]['injury']['strength']);

        $delivery = $this->deliveryRows();
        self::assertCount(2, $delivery);
        foreach ($delivery as $row) {
            self::assertSame('strike', $row['source']);
            self::assertSame([], $row['keys']);
        }
    }

    /**
     * Владелец атакующего объявляет, владелец защитника закрывает.
     *
     * @return void
     */
    public function testCharacterOwnerCanDeclareAndResolve(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $attackerOwner = $this->addUser('attacker-owner');
        $defenderOwner = $this->addUser('defender-owner');
        $stranger = $this->addUser('stranger-owner');
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $attackerOwner,
            'role' => 'player',
        ]));
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $defenderOwner,
            'role' => 'player',
        ]));
        $attackerId = $this->admit($world->getId(), $gameId, 'Hero', [], [], [], $attackerOwner);
        $defenderId = $this->admit($world->getId(), $gameId, 'Guard', [], [], [], $defenderOwner);
        $defenderVersion = $this->characterFacade()->get($defenderId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-owner',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $this->setActor($attackerOwner, []);
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-owner',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $this->setActor($attackerOwner, []);
        $wrong = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wrong',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertSame('AUTH_DENIED', $wrong['error']['code']);
        $this->setActor($defenderOwner, []);
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-owner',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $this->setActor($defenderOwner, []);
        $again = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-again',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertSame('GAME_INVALID', $again['error']['code']);
        $this->setActor($stranger, []);
        $foreign = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-foreign',
            'expectedVersion' => 2,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertSame('AUTH_DENIED', $foreign['error']['code']);
    }

    /**
     * NPC-атаку объявляет только писатель, NPC-защиту закрывает только писатель.
     *
     * @return void
     */
    public function testNpcStrikeStepsRequireWriter(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $player = $this->addUser('npc-player');
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [], [], [], $player);
        $characterVersion = $this->characterFacade()->get($characterId)->getActualVersion();
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $player,
            'role' => 'player',
        ]));
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        $attackerNpc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Raider',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $defenderBattle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-npc-defender',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        $this->setActor($player, []);
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 's-npc-defender',
            'expectedVersion' => 1,
            'attack' => $this->attack($characterId, $npc['data']['npcId']),
        ])['success']);
        $this->setActor($player, []);
        $npcDefense = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 'd-npc-defender',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertSame('AUTH_DENIED', $npcDefense['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 'd-npc-defender',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'ignore'],
        ])['success']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.endBattle', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 'e-npc-defender',
            'expectedVersion' => 3,
        ])['data']['ended']);
        $attackerBattle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-npc-attacker',
            'participants' => [
                ['type' => 'npc', 'id' => $attackerNpc['data']['npcId']],
                ['type' => 'character', 'id' => $characterId],
            ],
        ]);
        $this->setActor($player, []);
        $npcAttack = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $attackerBattle['data']['battleId'],
            'idempotencyKey' => 's-npc-attacker',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'npc', 'id' => $attackerNpc['data']['npcId']],
                'defender' => ['type' => 'character', 'id' => $characterId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ]);
        self::assertSame('AUTH_DENIED', $npcAttack['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $attackerBattle['data']['battleId'],
            'idempotencyKey' => 's-npc-attacker',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'npc', 'id' => $attackerNpc['data']['npcId']],
                'defender' => ['type' => 'character', 'id' => $characterId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ])['success']);
        $this->setActor($player, []);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($card['success'], json_encode($card));
        self::assertSame($attackerNpc['data']['npcId'], $card['data']['battle']['openStrike']['attackerId'] ?? null);
        self::assertSame($characterId, $card['data']['battle']['openStrike']['defenderId'] ?? null);
        self::assertTrue($this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $attackerBattle['data']['battleId'],
            'idempotencyKey' => 'd-npc-attacker',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $characterVersion,
            'defense' => ['reaction' => 'ignore'],
        ])['success']);
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
        $commandsBefore = $this->countCommands('strike');

        $results = $this->runConcurrentStrikeRequests('game.resolveStrike', $payload);
        $this->reconnectGameAfterFork();

        self::assertCount(2, $results);
        self::assertEquals($results[0]['data'], $results[1]['data']);
        self::assertTrue($results[0]['success']);
        self::assertTrue($results[1]['success']);
        self::assertSame($commandsBefore + 1, $this->countCommands('strike'));
        self::assertSame(1, $this->countActions('strike'));
        self::assertSame($npcVersion + 1, $this->dispatch('game.getNpc', [
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
        self::assertSame($commandsBefore + 1, $this->countCommands('strike'));
        self::assertSame(1, $this->countActions('strike'));
        self::assertSame($deliveryBefore + 1, $this->countAll(GameDeliveryTable::class));
    }

    /**
     * Писатель и владелец защитника конкурентно закрывают удар разными ключами.
     *
     * @return void
     */
    public function testConcurrentWriterAndDefenderOwnerResolveOnce(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            self::markTestSkipped('pcntl is required for concurrent close acceptance');
        }

        $world = $this->worldWithWeapon(1, true, 1, true);
        $gameId = $this->addGame($world->getId(), $this->worldRevision(), 'in_process');
        $defenderOwner = $this->addUser('concurrent-defender-owner');
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $defenderOwner,
            'role' => 'player',
        ]));
        $attackerId = $this->admit($world->getId(), $gameId, 'Concurrent-Writer-Hero');
        $defenderId = $this->admit(
            $world->getId(),
            $gameId,
            'Concurrent-Owner-Guard',
            [],
            [],
            [],
            $defenderOwner,
        );
        $defenderVersion = $this->characterFacade()->get($defenderId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'concurrent-owner-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'] ?? false, json_encode($battle));
        $battleId = $battle['data']['battleId'];
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'concurrent-owner-open',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ])['success']);
        $commandsBefore = $this->countCommands('strike');
        $writerPayload = [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'concurrent-owner-writer',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $defenderVersion,
            'defense' => ['reaction' => 'ignore'],
        ];
        $ownerPayload = $writerPayload;
        $ownerPayload['idempotencyKey'] = 'concurrent-owner-defender';

        $results = $this->runConcurrentStrikeRequests('game.resolveStrike', $writerPayload, [
            [
                'payload' => $writerPayload,
                'userId' => $this->ownerUserId,
                'keys' => [GamePermissionKeys::CREATE],
            ],
            [
                'payload' => $ownerPayload,
                'userId' => $defenderOwner,
                'keys' => [],
            ],
        ]);
        $this->reconnectGameAfterFork();

        self::assertCount(2, $results);
        $successes = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['success'] ?? false) === true,
        ));
        self::assertCount(1, $successes);
        $failures = array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['success'] ?? false) !== true,
        ));
        self::assertCount(1, $failures);
        self::assertContains($failures[0]['error']['code'] ?? null, ['GAME_CONFLICT', 'GAME_INVALID']);
        self::assertSame($commandsBefore + 1, $this->countCommands('strike'));
        self::assertSame(1, $this->countActions('strike'));
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
        self::assertSame(8, $closed['data']['success']);
        self::assertSame($this->pair(4), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame($this->pair(32), $closed['data']['injury']);
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
        self::assertSame($this->pair(1), $closed['data']['resistance']);
        self::assertSame($this->pair(36, -1), $closed['data']['injury']);
        self::assertSame(
            ['battleId', 'strikeId', 'version', 'attackerRoll', 'success', 'damage', 'resistance', 'sheetVersion', 'defenderRoll', 'S', 'injury', 'wound', 'exhaustionDelta', 'endurance', 'will', 'willOutcome', 'maim'],
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
        self::assertSame(4, $closed['success']);
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
        self::assertSame(4, $closed['success']);
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
     * Без карточки или с двумя карточками уклон отклоняется.
     * Одна карточка даёт базовую ловкость и без закупки.
     *
     * @return void
     */
    public function testResolveDodgeRequiresExactlyOneCardAndUsesBaseAgility(): void
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
        $closed = $this->resolveDefense($one->getId(), [
            'characteristicPurchases' => [],
        ], 'sword', true, 'dodge');
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(4, -1), $closed['S']);
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
        self::assertSame(0, $this->countActions('strike'));
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
        self::assertSame(1, $this->countOpenActions('strike'));
        self::assertSame(1, $this->countCommands('strike'));
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
        self::assertSame(0, $this->countActions('strike'));
        self::assertSame(0, $this->countCommands('strike'));
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
        $strike = $mysql->select("select item_inventory_id, item_rule_code, open from game_action where kind = 'strike'");
        self::assertSame(2, (int) $strike[0]->item_inventory_id);
        self::assertSame('sword', (string) $strike[0]->item_rule_code);
        $commandsBefore = $this->countCommands('strike');
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
        self::assertSame($commandsBefore, $this->countCommands('strike'));
        self::assertSame(1, (int) $mysql->select("select open from game_action where kind = 'strike'")[0]->open);
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
        self::assertSame([['base' => 5, 'current' => 0, 'ruleCode' => 'focus']], $afterDefender->getSheet()['resources']);
        self::assertSame($attackerVersion, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertSame(0, (int) $mysql->select("select open from game_action where kind = 'strike'")[0]->open);
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
        self::assertSame(1, (int) $mysql->select("select open from game_action where kind = 'strike'")[0]->open);
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
        bool $withPendingEffects = false,
        array $cardEffects = [],
        array $extraCards = [],
    ): RuleSpaceRecord {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $this->plainRollId($mechanics);
        $reliabilityCutId = $this->reliabilityCutId($mechanics);
        $world = $this->addWorldWithRevision(
            'razrabotka-' . $hitCards . ($withPool ? '-pool' : '-empty') . '-s' . $soakCards
            . ($withPenetration ? '-penetration' : '')
            . ($withBlockResource ? '-block' : '')
            . ($withPendingEffects ? '-pending' : '')
            . ($cardEffects === [] ? '' : '-fx' . substr(md5((string) json_encode($cardEffects)), 0, 6))
            . ($extraCards === [] ? '' : '-ex' . substr(md5((string) json_encode($extraCards)), 0, 6)),
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
        $swingComponents = $withPendingEffects
            ? [['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 2]]
            : [];
        $pendingEffects = $withPendingEffects
            ? [
                [
                    'type' => 'next_action_attack_cost',
                    'resource_code' => 'action-points',
                    'delta' => 1,
                ],
                [
                    'type' => 'after_action_until_resource_spent_check_modifier',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                    'check_codes' => ['hit'],
                    'delta' => -2,
                ],
            ]
            : [];
        $swingEffects = [...$pendingEffects, ...$cardEffects];
        $entries = [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => $this->raceCharacteristics($soakCards, $hitEfficiency),
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing', new RuleVersionBody('ability', 'Swing', '', [
                'type' => 'action',
                'action_components' => $swingComponents,
                ...($swingEffects === [] ? [] : ['action_effects' => $swingEffects]),
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('dodge', new RuleVersionBody('ability', 'Dodge', '', [
                'type' => 'action',
                'combat_action' => 'dodge',
                'dodge_efficiency' => ['base' => 4, 'size' => -1],
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
                    'weapon_profiles' => [[
                        'type' => 'strike',
                        'accuracy' => ['base' => $hitEfficiency === 0 ? 0 : 3, 'size' => 0],
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
                        'accuracy' => ['base' => 4, 'size' => 0],
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
                        'accuracy' => ['base' => 3, 'size' => 0],
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
            RuleCommitEntry::put('roll', new RuleVersionBody('simple', 'Roll', '', [], [], [[
                'mechanic_id' => $rollId,
                'mechanic_payload' => ['type' => 'roll', 'data' => $payload],
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
                        'accuracy' => ['base' => 3, 'size' => 0],
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
                        'accuracy' => ['base' => 3, 'size' => 0],
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
                'limit' => ['base' => 5, 'adjustments' => []],
            ], [], [], 'needs_work'));
            $entries[] = RuleCommitEntry::put('brace', new RuleVersionBody('ability', 'Brace', '', [
                'type' => 'action',
                'combat_action' => 'block',
                'action_components' => [[
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

        if ($withPendingEffects) {
            $entries[] = RuleCommitEntry::put('action-points', new RuleVersionBody('resource', 'Action points', '', [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'turn_refill' => true,
                'limit' => ['base' => 10, 'adjustments' => []],
            ], [], [], 'needs_work'));
        }

        foreach ($extraCards as $code => $definition) {
            $effects = $definition;
            $components = [];
            $combatAction = null;
            if (is_array($definition) && array_key_exists('effects', $definition)) {
                $effects = is_array($definition['effects']) ? $definition['effects'] : [];
                $components = is_array($definition['components'] ?? null) ? $definition['components'] : [];
                $combatAction = is_string($definition['combat_action'] ?? null) ? $definition['combat_action'] : null;
            }

            $entries[] = RuleCommitEntry::put($code, new RuleVersionBody('ability', ucfirst($code), '', [
                'type' => 'action',
                ...($combatAction === null ? [] : ['combat_action' => $combatAction]),
                'action_components' => $components,
                ...($effects === [] ? [] : ['action_effects' => $effects]),
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
     * Выбор атаки персонажа по персонажу.
     *
     * @param int $attackerId Атакующий.
     * @param int $defenderId Защитник.
     *
     * @return array<string, mixed> Тело.
     */
    private function characterAttack(
        int $attackerId,
        int $defenderId,
        string $actionRuleCode = 'swing',
    ): array {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'defender' => ['type' => 'character', 'id' => $defenderId],
            'actionRuleCode' => $actionRuleCode,
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
        ];
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
    private function raceCharacteristics(int $soakCards, ?int $hitEfficiency = null): array
    {
        $rows = [[
            'characteristic_code' => 'strength',
            'weapon_mastery' => ['profiles' => ['strike']],
            'mode' => 'purchased',
            'base' => ['base' => 3, 'size' => 0],
            'purchase' => [['cost' => 2, 'value' => $hitEfficiency === 0
                ? ['base' => 3, 'size' => -1]
                : ['base' => 4, 'size' => 0]]],
        ]];
        if ($soakCards >= 1) {
            $rows[] = [
                'characteristic_code' => 'agility',
                'mode' => 'purchased',
                'base' => ['base' => 4, 'size' => 0],
                'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
            ];
        }

        return $rows;
    }

    /**
     * Допущенный active.
     *
     * @param int $spaceId Мир.
     * @param int $gameId Игра.
     * @param string $name Имя.
     * @param array<int, array<string, mixed>> $purchases Закупки листа.
     * @param array<string, mixed> $sheet Дополнение листа.
     * @param array<int, array<string, mixed>> $inventory Инвентарь.
     * @param int|null $ownerUserId Владелец персонажа или владелец стенда.
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
        ?int $ownerUserId = null,
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
        $this->membershipFacade()->submit($gameId, $characterId, $ownerUserId);
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
     * @param string $action Action по умолчанию.
     * @param array<string, mixed> $payload JSON payload по умолчанию.
     * @param array<int, array{action?: string, payload?: array<string, mixed>, userId?: int, keys?: list<string>}> $workerOverrides
     *     Переопределения актора и запроса по индексу воркера.
     *
     * @return list<array<string, mixed>> Ответы.
     */
    private function runConcurrentStrikeRequests(string $action, array $payload, array $workerOverrides = []): array
    {
        $workers = [];
        foreach ([0, 1] as $workerIndex) {
            $override = $workerOverrides[$workerIndex] ?? [];
            $workerAction = is_string($override['action'] ?? null) ? $override['action'] : $action;
            $workerPayload = is_array($override['payload'] ?? null) ? $override['payload'] : $payload;
            $workerUserId = is_int($override['userId'] ?? null) ? $override['userId'] : $this->ownerUserId;
            $workerKeys = is_array($override['keys'] ?? null) ? $override['keys'] : [GamePermissionKeys::CREATE];
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
                $this->runConcurrentStrikeWorker(
                    $sockets[1],
                    $workerAction,
                    $workerPayload,
                    $workerUserId,
                    $workerKeys,
                );
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
     * @param int $actorUserId Актор запроса.
     * @param list<string> $keys Ключи прав актора.
     *
     * @return never
     */
    private function runConcurrentStrikeWorker(
        $socket,
        string $action,
        array $payload,
        int $actorUserId,
        array $keys,
    ): never {
        try {
            $this->bootGameApplication();
            $requestContext = $this->gameApplication()
                ->getLocator()
                ->get(IKernelContainer::class)
                ->get(IRequestContext::class);
            self::assertInstanceOf(IRequestContext::class, $requestContext);
            $this->requestContext = $requestContext;
            $this->setActor($actorUserId, $keys);
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

    /**
     * Текущие ОД персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return int Значение.
     */
    private function actionPointsOf(int $characterId): int
    {
        $resources = $this->characterFacade()->get($characterId)->getSheet()['resources'] ?? [];
        self::assertIsArray($resources);
        foreach ($resources as $row) {
            if (!is_array($row) || ($row['ruleCode'] ?? null) !== 'action-points') {
                continue;
            }

            $current = $row['current'] ?? null;
            if (is_int($current)) {
                return $current;
            }

            if (is_array($current) && is_int($current['base'] ?? null)) {
                return $current['base'];
            }
        }

        self::fail('Character action points are missing');
    }

    /**
     * Виды висящих эффектов актора боя.
     *
     * @param int $battleId Бой.
     * @param string $key Ключ актора.
     *
     * @return list<string> Виды по порядку карты.
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
     * Удар по персонажу карточкой с эффектами; первый бросок из чата.
     *
     * @param RuleSpaceRecord $world Мир.
     *
     * @return array<string, mixed> Payload броска атакующего.
     */
    private function strikeRollAfterCard(RuleSpaceRecord $world): array
    {
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-roll-adjust',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-roll-adjust',
            'expectedVersion' => 1,
            'attack' => $this->characterAttack($attackerId, $defenderId),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-roll-adjust',
            'expectedVersion' => $opened['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $rolls = $this->chatRolls($gameId);
        self::assertNotSame([], $rolls);

        return $rolls[0];
    }

    /**
     * Два удара карточкой с эффектами: броски и ожидание после первого.
     *
     * @param RuleSpaceRecord $world Мир.
     *
     * @return array{rolls: list<array<string, mixed>>, pending: array<int, mixed>, defenderId: int} Итог.
     */
    private function twoStrikesAfterCard(RuleSpaceRecord $world): array
    {
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-tail',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $version = 1;
        $pending = [];
        foreach ([1, 2] as $index) {
            $opened = $this->dispatch('game.declareStrike', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 's-tail-' . $index,
                'expectedVersion' => $version,
                'attack' => $this->characterAttack($attackerId, $defenderId),
            ]);
            self::assertTrue($opened['success'], json_encode($opened));
            $closed = $this->dispatch('game.resolveStrike', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'd-tail-' . $index,
                'expectedVersion' => $opened['data']['version'],
                'expectedSheetVersion' => $this->characterFacade()->get($defenderId)->getActualVersion(),
                'defense' => ['reaction' => 'ignore'],
            ]);
            self::assertTrue($closed['success'], json_encode($closed));
            $version = $closed['data']['version'];
            if ($index === 1) {
                $pending = $this->pendingEntries($battleId, 'character:' . $attackerId);
            }
        }

        return [
            'rolls' => $this->chatRolls($gameId),
            'pending' => $pending,
            'defenderId' => $defenderId,
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
     * Записи ожидания актора.
     *
     * @param int $battleId Бой.
     * @param string $key Ключ актора.
     *
     * @return array<int, mixed> Записи.
     */
    private function pendingEntries(int $battleId, string $key): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = $gateway->open(GameBattleTable::class)->records()->getById($battleId);
        self::assertIsArray($row);
        $pending = $row['pending_action_effects'] ?? [];
        self::assertIsArray($pending);

        $entries = $pending[$key] ?? [];
        self::assertIsArray($entries);

        return array_values($entries);
    }

    /**
     * Payload бросков из чата игры в порядке сообщений.
     *
     * @param int $gameId Игра.
     *
     * @return list<array<string, mixed>> Payload.
     */
    private function chatRolls(int $gameId): array
    {
        $rolls = [];
        foreach ($this->chatMessages($gameId) as $message) {
            foreach ($message->getAttachments() as $attachment) {
                $payload = is_array($attachment) ? ($attachment['payload'] ?? null) : null;
                if (($attachment['type'] ?? null) === 'roll' && is_array($payload)) {
                    $rolls[] = $payload;
                }
            }
        }

        return $rolls;
    }

    /**
     * Эффект ремапа граней карточки.
     *
     * @param int $from Откуда.
     * @param int $to Куда.
     *
     * @return array<string, mixed> Эффект.
     */
    private function remapEffect(int $from, int $to): array
    {
        return [
            'type' => 'current_action_roll_face_remap',
            'from' => $from,
            'to' => $to,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
        ];
    }

    /**
     * Эффект дельт успехов карточки.
     *
     * @param int $oneDelta Дельта единицы.
     * @param int $faceDelta Дельта грани.
     *
     * @return array<string, mixed> Эффект.
     */
    private function scoreEffect(int $oneDelta, int $faceDelta): array
    {
        return [
            'type' => 'current_action_roll_score_adjust',
            'oneDelta' => $oneDelta,
            'faceDelta' => $faceDelta,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
        ];
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
        return $this->messagesByThreadKind($gameId, 'attack');
    }

    /**
     * Сообщения игрового чата заданного вида треда.
     *
     * @param int $gameId Игра.
     * @param string $kind Вид треда.
     *
     * @return array<int, MessageRecord> Сообщения.
     */
    private function messagesByThreadKind(int $gameId, string $kind): array
    {
        $chatId = $this->gameFacade()->get($gameId)->getGameChatId();
        self::assertIsInt($chatId);
        $messages = array_reverse($this->chats()->findMessagePage($chatId, $this->ownerUserId, 100, 0)->getItems());

        return array_values(array_filter(
            $messages,
            fn (MessageRecord $message): bool => $this->threadKind($message) === $kind,
        ));
    }

    /**
     * Тред сообщения.
     *
     * @param MessageRecord $message Сообщение.
     *
     * @return array<string, mixed>|null Тред или null.
     */
    private function threadOf(MessageRecord $message): ?array
    {
        foreach ($message->getAttachments() as $attachment) {
            if (($attachment['type'] ?? null) !== 'chat_system') {
                continue;
            }
            $thread = $attachment['payload']['thread'] ?? null;
            if (is_array($thread)) {
                return $thread;
            }
        }

        return null;
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
