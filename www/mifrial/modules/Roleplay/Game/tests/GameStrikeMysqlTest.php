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
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Service\GameStrikeAmounts;
use Mifrial\Roleplay\Game\Table\GameBattleTable;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Game\Table\GameSessionTable;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Service\MechanicPortFactory;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId'])),
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
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $npcActual),
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
        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $npcActual),
        ]);
        self::assertEquals($closed['data'], $replay['data']);
        self::assertSame(1, $this->countActions('strike'));
        $differentBody = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $npcActual),
        ]);
        self::assertSame('GAME_CONFLICT', $differentBody['error']['code']);
    }

    /**
     * Каст списывает ОД, бьёт электричеством, пишет Шок, Волю и увечье.
     *
     * @return void
     */
    public function testSpellCastAppliesElectricityAndSpendsAp(): void
    {
        $scene = $this->spellScene();
        $gameId = $scene['gameId'];
        $hero = $scene['hero'];
        $heroVersion = $scene['heroVersion'];
        $targetVersion = $scene['targetVersion'];
        $body = $this->spellBody($scene);
        $cast = $this->dispatch('game.castSpell', $body);
        self::assertTrue($cast['success'], json_encode($cast));
        self::assertSame(4, $cast['data']['spent']);
        self::assertTrue($cast['data']['cast']['needsCheck']);
        self::assertTrue($cast['data']['cast']['roll']['success']);
        self::assertSame(2, $cast['data']['spellSr']);
        self::assertSame(['base' => 4, 'size' => 0], $cast['data']['spellDamage']);
        self::assertNotNull($cast['data']['injury']);
        self::assertGreaterThan($heroVersion, $cast['data']['caster']['sheetVersion']);
        self::assertGreaterThan($targetVersion, $cast['data']['target']['sheetVersion']);
        $consequence = $cast['data']['consequence'];
        self::assertArrayHasKey('will', $consequence);
        self::assertArrayHasKey('maim', $consequence);
        self::assertSame(2, $consequence['exhaustionDelta']);

        $heroAfter = $cast['data']['caster']['sheetVersion'];
        $targetAfter = $cast['data']['target']['sheetVersion'];
        $replay = $this->dispatch('game.castSpell', $body);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertEquals($cast['data'], $replay['data']);
        self::assertSame($heroAfter, $this->characterFacade()->get($hero)->getActualVersion());
        self::assertSame(
            $targetAfter,
            $this->dispatch('game.getNpc', ['gameId' => $gameId, 'npcId' => $scene['npcId']])
                ['data']['actualVersion'],
        );

        $messages = $this->chatMessages($gameId);
        $contents = array_map(static fn (MessageRecord $message): string => $message->getContent(), $messages);
        self::assertSame([], array_values(array_filter($contents, static fn (string $text): bool => str_contains($text, '|'))));
        $damageIndex = $this->messageIndexOf($contents, 'поражает');
        $thread = $this->threadOf($messages[$damageIndex]);
        self::assertNotNull($thread);
        self::assertSame('spell', $thread['kind']);
        self::assertStringStartsWith('b' . $scene['battleId'] . '-r', $thread['parentId']);
        self::assertNotSame($thread['id'], $thread['parentId']);
        $exhaustionIndex = $this->messageIndexOf($contents, 'истощени');
        self::assertStringContainsString('сложностью', $contents[$exhaustionIndex]);
        $this->messageIndexOf($contents, '[[rule:shock]]');
        $this->messageIndexOf($contents, 'истощение (');
        $this->messageIndexOf($contents, 'увечье');
    }

    /**
     * Заклинание без касания идёт через единый вход game.runAction kind spell.
     *
     * @return void
     */
    public function testRunActionSpellCastsLightningStrike(): void
    {
        $scene = $this->spellScene();
        $body = [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'run-spell-1',
            'expectedVersion' => 1,
            'action' => [
                'phase' => 'declare',
                'kind' => 'spell',
                'code' => 'lightning-strike',
                'actor' => ['type' => 'character', 'id' => $scene['hero']],
                'context' => [
                    'expectedSheetVersion' => $scene['heroVersion'],
                    'pathCode' => 'arcanist',
                    'parameters' => ['x' => ['base' => 4, 'size' => 0]],
                    'usedPower' => [
                        'source' => ['kind' => 'characteristic', 'code' => 'magic-power'],
                        'value' => ['base' => 5, 'size' => 1],
                    ],
                    'target' => ['type' => 'npc', 'id' => $scene['npcId']],
                    'expectedTargetVersion' => $scene['targetVersion'],
                ],
            ],
        ];
        $cast = $this->dispatch('game.runAction', $body);
        self::assertTrue($cast['success'], json_encode($cast));
        self::assertSame('spell', $cast['data']['kind']);
        self::assertSame(4, $cast['data']['spent']);
        self::assertTrue($cast['data']['cast']['needsCheck']);
        self::assertTrue($cast['data']['cast']['roll']['success']);
        self::assertSame(2, $cast['data']['spellSr']);
        self::assertSame(['base' => 4, 'size' => 0], $cast['data']['spellDamage']);
        self::assertNotNull($cast['data']['injury']);

        $replay = $this->dispatch('game.runAction', $body);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertEquals($cast['data'], $replay['data']);
        self::assertSame(
            $cast['data']['caster']['sheetVersion'],
            $this->characterFacade()->get($scene['hero'])->getActualVersion(),
        );
    }

    /**
     * Каст без цели при targeting entity отклоняется до записи.
     *
     * @return void
     */
    public function testSpellCastRequiresTarget(): void
    {
        $scene = $this->spellScene();
        $body = $this->spellBody($scene);
        unset($body['target'], $body['expectedTargetVersion']);
        $cast = $this->dispatch('game.castSpell', $body);
        self::assertFalse($cast['success']);
        self::assertSame('INVALID_PARAMS', $cast['error']['code']);
        self::assertSame($scene['heroVersion'], $this->characterFacade()->get($scene['hero'])->getActualVersion());
    }

    /**
     * Stale цель откатывает и списание ОД.
     *
     * @return void
     */
    public function testSpellCastStaleTargetRollsBackAp(): void
    {
        $scene = $this->spellScene();
        $body = $this->spellBody($scene);
        $body['expectedTargetVersion'] = $scene['targetVersion'] + 5;
        $cast = $this->dispatch('game.castSpell', $body);
        self::assertFalse($cast['success']);
        self::assertSame('GAME_CONFLICT', $cast['error']['code']);
        self::assertSame($scene['heroVersion'], $this->characterFacade()->get($scene['hero'])->getActualVersion());
        self::assertSame(
            $scene['targetVersion'],
            $this->dispatch('game.getNpc', ['gameId' => $scene['gameId'], 'npcId' => $scene['npcId']])
                ['data']['actualVersion'],
        );
    }

    /**
     * Значение параметра вне min/max отклоняется.
     *
     * @return void
     */
    public function testSpellCastRejectsParameterOutOfRange(): void
    {
        $scene = $this->spellScene();
        $body = $this->spellBody($scene);
        $body['parameters'] = ['x' => ['base' => 5, 'size' => 2]];
        $cast = $this->dispatch('game.castSpell', $body);
        self::assertFalse($cast['success']);
        self::assertSame('GAME_INVALID', $cast['error']['code']);
        self::assertSame($scene['heroVersion'], $this->characterFacade()->get($scene['hero'])->getActualVersion());
    }

    /**
     * Используемая мощь выше характеристики пути отклоняется.
     *
     * @return void
     */
    public function testSpellCastRejectsUsedPowerAboveCap(): void
    {
        $scene = $this->spellScene();
        $body = $this->spellBody($scene);
        $body['usedPower']['value'] = ['base' => 5, 'size' => 2];
        $cast = $this->dispatch('game.castSpell', $body);
        self::assertFalse($cast['success']);
        self::assertSame('GAME_INVALID', $cast['error']['code']);
        self::assertSame($scene['heroVersion'], $this->characterFacade()->get($scene['hero'])->getActualVersion());
    }

    /**
     * Без ОД каст отклоняется до записи.
     *
     * @return void
     */
    public function testSpellCastWithoutApIsRejected(): void
    {
        $scene = $this->spellScene(0);
        $cast = $this->dispatch('game.castSpell', $this->spellBody($scene));
        self::assertFalse($cast['success']);
        self::assertSame('GAME_INVALID', $cast['error']['code']);
        self::assertSame($scene['heroVersion'], $this->characterFacade()->get($scene['hero'])->getActualVersion());
    }

    /**
     * Касание идёт через game.runAction: удар, защита, затем проверка и эффект.
     *
     * @return void
     */
    public function testTouchSpellDeclaresStrikeAndCastsAfterDefense(): void
    {
        $scene = $this->spellScene();
        $beforeAp = $this->resourceCurrent($scene['hero'], 'action-points');
        $attack = [
            'attacker' => ['type' => 'character', 'id' => $scene['hero']],
            'defender' => ['type' => 'npc', 'id' => $scene['npcId']],
            'actionRuleCode' => 'swing',
            'itemInventoryId' => 1,
            'itemRuleCode' => 'sword',
            'profileType' => 'strike',
            'profileIndex' => 0,
            'spellCast' => [
                'spellCode' => 'discharge',
                'pathCode' => 'arcanist',
                'parameters' => ['x' => ['base' => 4, 'size' => 0]],
                'usedPower' => [
                    'source' => ['kind' => 'characteristic', 'code' => 'magic-power'],
                    'value' => ['base' => 5, 'size' => 1],
                ],
            ],
        ];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'touch-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($attack),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertFalse($opened['data']['debt'] ?? false);
        self::assertSame(2, $opened['data']['version']);
        self::assertSame($beforeAp - 4, $this->resourceCurrent($scene['hero'], 'action-points'));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'touch-d',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $scene['targetVersion']),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertGreaterThan(0, $closed['data']['success']);
        self::assertArrayHasKey('spellCast', $closed['data']);
        self::assertTrue($closed['data']['spellCast']['castOk']);
        self::assertNotNull($closed['data']['spellSr']);
        self::assertNotNull($closed['data']['spellDamage']);
        self::assertNotNull($closed['data']['spellInjury']);
        self::assertGreaterThan($scene['targetVersion'], $closed['data']['spellTargetVersion']);

        $messages = $this->chatMessages($scene['gameId']);
        $contents = array_map(static fn (MessageRecord $message): string => $message->getContent(), $messages);
        $declaration = $this->messageIndexOf($contents, 'требует касания');
        $touch = $this->messageIndexOf($contents, 'совершает касание');
        $check = $this->messageIndexOf($contents, 'проходит проверку на сотворение');
        $success = $this->messageIndexOf($contents, 'успешно сотворяет');
        self::assertSame(
            [true, true, true],
            [$declaration < $touch, $touch < $check, $check < $success],
            json_encode($contents, JSON_UNESCAPED_UNICODE),
        );
        self::assertStringContainsString('discharge', $contents[$declaration]);

        $spellThread = $this->threadOf($messages[$declaration]);
        $attackThread = $this->threadOf($messages[$touch]);
        $castThread = $this->threadOf($messages[$check]);
        self::assertNotNull($spellThread);
        self::assertNotNull($attackThread);
        self::assertNotNull($castThread);
        self::assertSame('spell', $spellThread['kind']);
        self::assertSame('spell-' . $opened['data']['strikeId'], $spellThread['id']);
        self::assertSame($spellThread['id'], $attackThread['parentId']);
        self::assertSame($spellThread['id'], $castThread['id']);
        self::assertStringStartsWith('b' . $scene['battleId'] . '-r1-t', $spellThread['parentId']);

        $damageIndex = $this->messageIndexOf($contents, 'поражает');
        self::assertStringNotContainsString('|', $contents[$damageIndex]);
        self::assertSame($spellThread['id'], $this->threadOf($messages[$damageIndex])['id'] ?? null);
        $totalIndex = $this->messageIndexOf($contents, 'итого');
        self::assertStringContainsString('сложностью', $contents[$totalIndex]);
        self::assertStringNotContainsString('проверяет Волю', implode("\n", $contents));
        $this->messageIndexOf($contents, '[[rule:shock]]');
        $this->messageIndexOf($contents, 'истощение (');
    }

    /**
     * Стоимость касания — максимум из удара с надбавкой и заклинания.
     *
     * @return void
     */
    public function testTouchSpellCostsMaxOfAdjustedStrikeAndSpell(): void
    {
        $scene = $this->spellScene(10);
        $first = $this->dispatch('game.runAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'touch-cost-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($scene['hero'], $scene['npcId'])),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'touch-cost-d',
            'expectedVersion' => $first['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $scene['targetVersion']),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $before = $this->resourceCurrent($scene['hero'], 'action-points');
        self::assertSame(8, $before);

        $touch = $this->attack($scene['hero'], $scene['npcId']);
        $touch['spellCast'] = [
            'spellCode' => 'discharge',
            'pathCode' => 'arcanist',
            'parameters' => ['x' => ['base' => 4, 'size' => 0]],
            'usedPower' => [
                'source' => ['kind' => 'characteristic', 'code' => 'magic-power'],
                'value' => ['base' => 5, 'size' => 1],
            ],
        ];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'touch-cost-2',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope($touch),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(4, $opened['data']['spent']);
        self::assertSame($before - 4, $this->resourceCurrent($scene['hero'], 'action-points'));

        $contents = array_map(
            static fn (MessageRecord $message): string => $message->getContent(),
            $this->chatMessages($scene['gameId']),
        );
        $declaration = $this->messageIndexOf($contents, 'требует касания');
        self::assertStringContainsString('за 4 ОД', $contents[$declaration]);
    }

    /**
     * Тело каста сцены.
     *
     * @param array{
     *     gameId: int,
     *     battleId: int,
     *     hero: int,
     *     heroVersion: int,
     *     npcId: int,
     *     targetVersion: int
     * } $scene Сцена.
     *
     * @return array<string, mixed> Тело.
     */
    private function spellBody(array $scene): array
    {
        return [
            'gameId' => $scene['gameId'],
            'battleId' => $scene['battleId'],
            'idempotencyKey' => 'cast-1',
            'expectedVersion' => 1,
            'expectedSheetVersion' => $scene['heroVersion'],
            'expectedTargetVersion' => $scene['targetVersion'],
            'actor' => ['type' => 'character', 'id' => $scene['hero']],
            'spellCode' => 'lightning-strike',
            'pathCode' => 'arcanist',
            'parameters' => ['x' => ['base' => 4, 'size' => 0]],
            'usedPower' => [
                'source' => ['kind' => 'characteristic', 'code' => 'magic-power'],
                'value' => ['base' => 5, 'size' => 1],
            ],
            'target' => ['type' => 'npc', 'id' => $scene['npcId']],
        ];
    }

    /**
     * Сцена каста: герой, NPC и бой.
     *
     * @return array{
     *     gameId: int,
     *     battleId: int,
     *     hero: int,
     *     heroVersion: int,
     *     npcId: int,
     *     targetVersion: int
     * } Сцена.
     */
    private function spellScene(int $ap = 10): array
    {
        $world = $this->spellWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $hero = $this->admit(
            $world->getId(),
            $gameId,
            'Hero',
            [],
            ['resources' => [['ruleCode' => 'action-points', 'current' => $ap]]],
            [],
            null,
            null,
            [
                ['ruleCode' => 'discharge', 'level' => 1, 'domainCode' => 'arcanist'],
                ['ruleCode' => 'lightning-strike', 'level' => 1, 'domainCode' => 'arcanist'],
            ],
        );
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
                ['type' => 'character', 'id' => $hero],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'hero' => $hero,
            'heroVersion' => $this->characterFacade()->get($hero)->getActualVersion(),
            'npcId' => $npc['data']['npcId'],
            'targetVersion' => $npc['data']['actualVersion'],
        ];
    }

    /**
     * Мир каста: путь, проверка, заклинание, электричество и Шок.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function spellWorld(): RuleSpaceRecord
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        try {
            $shockId = $mechanics->add('exhaustion_to_shock', 'Shock', '', '1.0.0');
        } catch (MechanicInvalidException) {
            $shockId = $mechanics->getByCodeVersion('exhaustion_to_shock', '1.0.0')->getId();
        }

        return $this->worldWithWeapon(
            1,
            true,
            0,
            false,
            null,
            false,
            true,
            [],
            [
                'discharge' => [
                    'type' => 'spell',
                    'effects' => [],
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                    'spec' => [
                        'spell' => [
                            'power' => ['type' => 'parameter', 'parameter_code' => 'x'],
                            'control' => ['base' => 3, 'size' => -1],
                            'targeting' => 'none',
                            'duration' => ['type' => 'instant'],
                            'damage' => [
                                'damage_type_code' => 'electricity',
                                'experience_keyword_code' => 'electromancy',
                                'power_modify_steps' => [['modify' => 3, 'min_experience' => 0]],
                            ],
                        ],
                        'parameters' => [[
                            'code' => 'x',
                            'label' => 'X',
                            'kind' => 'dimensional',
                            'resolution' => 'activation',
                            'default' => ['base' => 3, 'size' => 0],
                            'min' => ['base' => 3, 'size' => -1],
                            'max' => ['base' => 5, 'size' => 1],
                        ]],
                        'hit_resolution' => ['type' => 'attack'],
                        'action_components' => [[
                            'type' => 'resource',
                            'resource_code' => 'action-points',
                            'amount' => 4,
                        ], [
                            'type' => 'somatic',
                            'note' => 'touch',
                        ]],
                    ],
                ],
                'lightning-strike' => [
                    'type' => 'spell',
                    'effects' => [],
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                    'requirements' => [[
                        'level' => 1,
                        'requirements' => [['type' => 'has_ability', 'ability_code' => 'discharge']],
                    ]],
                    'spec' => [
                        'spell' => [
                            'power' => ['type' => 'parameter', 'parameter_code' => 'x'],
                            'control' => ['base' => 3, 'size' => -1],
                            'targeting' => 'entity',
                            'duration' => ['type' => 'instant'],
                            'damage' => [
                                'damage_type_code' => 'electricity',
                                'experience_keyword_code' => 'electromancy',
                                'power_modify_steps' => [['modify' => 0, 'min_experience' => 0]],
                                'falloff' => [
                                    'free_ipari' => 2,
                                    'size_per_extra_ipari' => 1,
                                    'min' => ['base' => 3, 'size' => -1],
                                ],
                            ],
                        ],
                        'parameters' => [[
                            'code' => 'x',
                            'label' => 'X',
                            'kind' => 'dimensional',
                            'resolution' => 'activation',
                            'default' => ['base' => 3, 'size' => 0],
                            'min' => ['base' => 3, 'size' => -1],
                            'max' => ['base' => 5, 'size' => 1],
                        ]],
                        'hit_resolution' => ['type' => 'auto', 'rating' => 2],
                        'action_components' => [[
                            'type' => 'resource',
                            'resource_code' => 'action-points',
                            'amount' => 4,
                        ]],
                    ],
                ],
            ],
            null,
            [
                'intellect' => [
                    'type' => 'characteristic',
                    'spec' => ['automatic' => ['value' => ['base' => 3, 'size' => 0]]],
                ],
                'magic-power' => [
                    'type' => 'characteristic',
                    'spec' => ['automatic' => ['value' => ['base' => 5, 'size' => 1]]],
                ],
                'magic-control' => [
                    'type' => 'characteristic',
                    'spec' => ['automatic' => ['value' => ['base' => 3, 'size' => 0]]],
                ],
                'shock' => ['type' => 'state', 'spec' => ['value_type' => 'number', 'aggregation' => 'sum']],
                'cast-check' => ['type' => 'check', 'spec' => [
                    'allowed_modes' => 'both',
                    'difficulty_input' => ['kind' => 'ask'],
                    'characteristic_code' => 'intellect',
                    'allow_characteristic_override' => true,
                ]],
                'arcanist' => ['type' => 'magic_path', 'spec' => [
                    'check_code' => 'cast-check',
                    'cast_check_code' => 'cast-check',
                    'power_characteristic_code' => 'magic-power',
                    'control_characteristic_code' => 'magic-control',
                    'includes_path_codes' => [],
                ]],
                'electricity' => [
                    'type' => 'damage_type',
                    'mechanics' => [[
                        'mechanic_id' => $shockId,
                        'mechanic_payload' => ['stateRuleCode' => 'shock'],
                    ]],
                    'spec' => [
                        'forms' => ['genitive' => 'electricity', 'dative' => 'electricity'],
                        'defense_ignored' => true,
                        'max_success_rating' => 3,
                    ],
                ],
            ],
        );
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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-pending-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(8, $this->actionPointsOf($attackerId));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-pending-1',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $this->characterFacade()->get($defenderId)->getActualVersion()),
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
        $second = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-pending-2',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        self::assertSame(5, $this->actionPointsOf($attackerId));
        self::assertSame(
            ['after_action_until_resource_spent_check_modifier', 'last_strike_snapshot'],
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
        $closedAgain = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-pending-2',
            'expectedVersion' => $second['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
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
     * Жетон концентрации даёт преимущество на попадание и списывается.
     *
     * @return void
     */
    public function testConcentrationTokenAddsHitAdvantage(): void
    {
        $world = $this->worldWithWeapon(
            hitConcentration: true,
            extraRules: [
                'concentration' => [
                    'type' => 'resource',
                    'name' => 'Concentration',
                    'spec' => [
                        'is_dimensional' => false,
                        'auto_add' => false,
                        'check_token' => true,
                        'limit' => ['base' => 2, 'adjustments' => []],
                    ],
                ],
                'peak' => [
                    'type' => 'ability',
                    'name' => 'Peak',
                    'spec' => [
                        'type' => 'skill',
                        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                        'grants' => [],
                        'peak_concentration' => true,
                        'will_focus' => false,
                        'long_tension' => false,
                    ],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Concentrated', sheet: [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ], abilities: [['ruleCode' => 'peak', 'level' => 1]]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-concentration',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $envelope = $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId));
        $envelope['context']['tokenSpend'] = ['amount' => 1, 'mode' => 'advantage'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-concentration',
            'expectedVersion' => 1,
            'action' => $envelope,
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-concentration',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $advantages = $closed['data']['strikes'][0]['roll']['advantages'] ?? [];
        $this->assertAdvantage('concentration', 1, $advantages);
        $resources = $this->characterFacade()->get($attackerId)->getSheet()['resources'] ?? [];
        $current = null;
        foreach ($resources as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === 'concentration') {
                $current = $row['current'] ?? null;
            }
        }
        self::assertSame(1, $current);
    }

    /**
     * Расчётливая атака переводит жетон в Точность.
     *
     * @return void
     */
    public function testConcentrationAccuracyCard(): void
    {
        $world = $this->worldWithWeapon(
            hitConcentration: true,
            extraRules: [
                'concentration' => [
                    'type' => 'resource',
                    'name' => 'Concentration',
                    'spec' => [
                        'is_dimensional' => false,
                        'auto_add' => false,
                        'check_token' => true,
                        'limit' => ['base' => 2, 'adjustments' => []],
                    ],
                ],
                'peak' => [
                    'type' => 'ability',
                    'name' => 'Peak',
                    'spec' => [
                        'type' => 'skill',
                        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                        'grants' => [],
                        'peak_concentration' => true,
                        'will_focus' => false,
                        'long_tension' => false,
                    ],
                ],
                'raschetlivaya' => [
                    'type' => 'ability',
                    'name' => 'Calculated',
                    'spec' => [
                        'type' => 'skill',
                        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                        'grants' => [],
                        'token_spend' => [
                            'resource_code' => 'concentration',
                            'check' => 'hit',
                            'bonus' => ['target' => 'accuracy', 'amount' => 1],
                            'min_mastery' => ['base' => 4, 'size' => 0],
                            'source' => 'kontsentratsiya',
                        ],
                    ],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Calculated', sheet: [
            'abilityLevels' => ['peak' => 1, 'raschetlivaya' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ], abilities: [
            ['ruleCode' => 'peak', 'level' => 1],
            ['ruleCode' => 'raschetlivaya', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-accuracy',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $envelope = $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId));
        $envelope['context']['tokenSpend'] = ['amount' => 1, 'mode' => 'accuracy'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-accuracy',
            'expectedVersion' => 1,
            'action' => $envelope,
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-accuracy',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $advantages = $closed['data']['strikes'][0]['roll']['advantages'] ?? [];
        self::assertNotContains(['source_code' => 'concentration', 'delta' => 1], $advantages);
        $resources = $this->characterFacade()->get($attackerId)->getSheet()['resources'] ?? [];
        $current = null;
        foreach ($resources as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === 'concentration') {
                $current = $row['current'] ?? null;
            }
        }
        self::assertSame(1, $current);
    }

    /**
     * Жетон концентрации защиты даёт преимущество на уклонение и списывается.
     *
     * @return void
     */
    public function testDefenderConcentrationTokenAddsDefenseAdvantage(): void
    {
        $world = $this->worldWithWeapon(
            hitConcentration: true,
            extraRules: [
                'concentration' => [
                    'type' => 'resource',
                    'name' => 'Concentration',
                    'spec' => [
                        'is_dimensional' => false,
                        'auto_add' => false,
                        'check_token' => true,
                        'limit' => ['base' => 2, 'adjustments' => []],
                    ],
                ],
                'peak' => [
                    'type' => 'ability',
                    'name' => 'Peak',
                    'spec' => [
                        'type' => 'skill',
                        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                        'grants' => [],
                        'peak_concentration' => true,
                        'will_focus' => false,
                        'long_tension' => false,
                    ],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Concentrated', sheet: [
            'abilityLevels' => ['peak' => 1],
            'resources' => [['ruleCode' => 'concentration', 'current' => 2]],
        ], abilities: [['ruleCode' => 'peak', 'level' => 1]]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-defense-concentration',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-defense-concentration',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-defense-concentration',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge', 'spendConcentration' => 1],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $advantages = $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? [];
        $concentration = array_values(array_filter(
            $advantages,
            static fn (mixed $row): bool => is_array($row) && ($row['source_code'] ?? null) === 'concentration',
        ));
        self::assertCount(1, $concentration);
        self::assertSame(1, $concentration[0]['delta'] ?? null);
        $resources = $this->characterFacade()->get($defenderId)->getSheet()['resources'] ?? [];
        $current = null;
        foreach ($resources as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === 'concentration') {
                $current = $row['current'] ?? null;
            }
        }
        self::assertSame(1, $current);
    }

    /**
     * Метка боя даёт помеху защите и снимается после реальной защиты.
     *
     * @return void
     */
    public function testBattleMarkAddsDefensePenaltyAndIsConsumed(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Mark-Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Mark-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-mark-defense',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $this->seedBattleMarks($battleId, [$this->battleMark($defenderId, $attackerId)]);
        self::assertCount(1, $this->battleRow($battleId)['marks'] ?? []);

        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-mark-defense',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        self::assertCount(1, $this->battleRow($battleId)['marks'] ?? []);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-mark-defense',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $advantages = $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? [];
        $penalty = array_values(array_filter(
            $advantages,
            static fn (mixed $row): bool => is_array($row)
                && ($row['source_code'] ?? null) === 'circumstances'
                && ($row['delta'] ?? null) === -2,
        ));
        self::assertCount(1, $penalty, json_encode($closed['data']));
        self::assertSame([], $this->battleRow($battleId)['marks'] ?? []);
    }

    /**
     * «Игнорирование» не тратит метку.
     *
     * @return void
     */
    public function testBattleMarkSurvivesIgnore(): void
    {
        $world = $this->worldWithWeapon();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Ignore-Attacker');
        $defenderId = $this->admit($world->getId(), $gameId, 'Ignore-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-mark-ignore',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $this->seedBattleMarks($battleId, [$this->battleMark($defenderId, $attackerId)]);

        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-mark-ignore',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-mark-ignore',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertCount(1, $this->battleRow($battleId)['marks'] ?? []);
    }

    /**
     * Обманный манёвр: раскрытие, авто-провал и метка.
     *
     * @return void
     */
    public function testManeuverRevealAutoFailsAndPlacesMark(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->maneuverRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Attacker', abilities: [
            ['ruleCode' => 'obmannyy-manevr', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-maneuver',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $attack = $this->characterAttack($attackerId, $defenderId)
            + ['maneuverRuleCode' => 'obmannyy-manevr'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-maneuver',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($attack),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-maneuver',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertFalse($closed['data']['closed'] ?? true);
        self::assertSame('maneuver_reveal', $closed['data']['awaiting'][0]['kind'] ?? null);
        self::assertSame('check', $closed['data']['awaiting'][0]['phase'] ?? null);

        $awaiting = $closed['data']['awaiting'][0];
        $option = $awaiting['options'][0];
        $deliveryBefore = count($this->deliveryRows());
        $resolved = $this->dispatch('game.resolveStrikeWindow', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'w-maneuver',
            'expectedVersion' => $closed['data']['version'],
            'window' => [
                'actor' => $awaiting['actor'],
                'check' => $option['check'],
                'characteristic' => $option['characteristic'],
            ],
        ]);
        self::assertTrue($resolved['success'], json_encode($resolved));
        self::assertSame($deliveryBefore + 1, count($this->deliveryRows()));

        if (($resolved['data']['closed'] ?? true) === false) {
            $decision = $resolved['data']['awaiting'][0] ?? null;
            self::assertSame('decision', $decision['phase'] ?? null);
            $resolved = $this->dispatch('game.resolveStrikeWindow', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'w-maneuver-keep',
                'expectedVersion' => $resolved['data']['version'],
                'window' => [
                    'actor' => $decision['actor'],
                    'decision' => 'keep',
                ],
            ]);
            self::assertTrue($resolved['success'], json_encode($resolved));
        }

        self::assertTrue($resolved['data']['closed'] ?? false, json_encode($resolved));
        self::assertSame(0, $resolved['data']['success'] ?? null);
        $marks = $this->battleRow($battleId)['marks'] ?? [];
        self::assertCount(1, $marks);
        self::assertSame($defenderId, $marks[0]['subject']['id'] ?? null);
        self::assertSame($attackerId, $marks[0]['scope']['defense_against']['id'] ?? null);
        self::assertSame(-2, $marks[0]['value'] ?? null);

        $contents = array_map(
            static fn (MessageRecord $message): string => $message->getContent(),
            $this->chatMessages($gameId),
        );
        self::assertNotSame([], array_filter(
            $contents,
            static fn (string $text): bool => str_contains($text, '[[rule:obmannyy-manevr]]')
                && str_contains($text, 'атака проваливается'),
        ));
    }

    /**
     * Повтор объявления с манёвром по тому же ключу — replay, не GAME_CONFLICT.
     *
     * @return void
     */
    public function testManeuverDeclareReplaySameKeyAndBody(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->maneuverRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Replay-Attacker', abilities: [
            ['ruleCode' => 'obmannyy-manevr', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Replay-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-maneuver-replay',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $action = $this->strikeEnvelope(
            $this->characterAttack($attackerId, $defenderId)
                + ['maneuverRuleCode' => 'obmannyy-manevr'],
        );
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-maneuver-replay',
            'expectedVersion' => 1,
            'action' => $action,
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $sheetVersion = $this->characterFacade()->get($attackerId)->getActualVersion();
        $marksBefore = $this->battleRow($battleId)['marks'] ?? [];

        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-maneuver-replay',
            'expectedVersion' => 1,
            'action' => $action,
        ]);

        self::assertTrue($replay['success'], json_encode($replay));
        self::assertEquals($declared['data'], $replay['data']);
        self::assertSame($sheetVersion, $this->characterFacade()->get($attackerId)->getActualVersion());
        self::assertSame($marksBefore, $this->battleRow($battleId)['marks'] ?? []);
    }

    /**
     * Строго победивший может отказаться: метки и реакции нет.
     *
     * @return void
     */
    public function testManeuverStrictWinnerMayRefuse(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->maneuverRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Win-Attacker', abilities: [
            ['ruleCode' => 'obmannyy-manevr', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Win-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-maneuver-win',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-maneuver-win',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId)
                    + ['maneuverRuleCode' => 'obmannyy-manevr'],
            ),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-maneuver-win',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $awaiting = $closed['data']['awaiting'][0] ?? null;
        self::assertIsArray($awaiting);
        $option = null;
        foreach ($awaiting['options'] as $candidate) {
            if (($candidate['characteristic'] ?? null) === 'strength') {
                $option = $candidate;
            }
        }
        self::assertIsArray($option);

        $revealed = $this->dispatch('game.resolveStrikeWindow', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'w-maneuver-win',
            'expectedVersion' => $closed['data']['version'],
            'window' => [
                'actor' => $awaiting['actor'],
                'check' => $option['check'],
                'characteristic' => $option['characteristic'],
            ],
        ]);
        self::assertTrue($revealed['success'], json_encode($revealed));
        self::assertFalse($revealed['data']['closed'] ?? true);
        $decision = $revealed['data']['awaiting'][0] ?? null;
        self::assertSame('decision', $decision['phase'] ?? null);

        $refused = $this->dispatch('game.resolveStrikeWindow', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'w-maneuver-refuse',
            'expectedVersion' => $revealed['data']['version'],
            'window' => [
                'actor' => $decision['actor'],
                'decision' => 'refuse',
            ],
        ]);
        self::assertTrue($refused['success'], json_encode($refused));
        self::assertTrue($refused['data']['closed'] ?? false, json_encode($refused));
        self::assertSame(0, $refused['data']['success'] ?? null);
        self::assertSame([], $this->battleRow($battleId)['marks'] ?? []);
    }

    /**
     * Без реакций манёвр проваливает атаку без проверок и меток.
     *
     * @return void
     */
    public function testManeuverWithoutReactionsAutoFails(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->maneuverRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Empty-Attacker', abilities: [
            ['ruleCode' => 'obmannyy-manevr', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Empty-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-maneuver-empty',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-maneuver-empty',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId)
                    + ['maneuverRuleCode' => 'obmannyy-manevr'],
            ),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-maneuver-empty',
            'expectedVersion' => $declared['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertTrue($closed['data']['closed'] ?? false);
        self::assertSame(0, $closed['data']['success'] ?? null);
        self::assertSame([], $this->battleRow($battleId)['marks'] ?? []);
    }

    /**
     * Повторная защита при открытом окне манёвра отклоняется.
     *
     * @return void
     */
    public function testManeuverRejectsRepeatedDefense(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->maneuverRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Repeat-Attacker', abilities: [
            ['ruleCode' => 'obmannyy-manevr', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Repeat-Defender');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-maneuver-repeat',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-maneuver-repeat',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId)
                    + ['maneuverRuleCode' => 'obmannyy-manevr'],
            ),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));
        $defense = $this->strikeDefenseEnvelope(
            ['reaction' => 'dodge'],
            $this->characterFacade()->get($defenderId)->getActualVersion(),
        );
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-maneuver-repeat',
            'expectedVersion' => $declared['data']['version'],
            'action' => $defense,
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame('maneuver_reveal', $closed['data']['awaiting'][0]['kind'] ?? null);

        $repeated = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-maneuver-repeat-again',
            'expectedVersion' => $closed['data']['version'],
            'action' => $defense,
        ]);
        self::assertFalse($repeated['success'], json_encode($repeated));
        self::assertSame('GAME_INVALID', $repeated['error']['code'] ?? null);
        self::assertSame('Game maneuver window is open', $repeated['error']['message'] ?? null);
    }

    /**
     * Манёвр после удара: цель получает Неустойчивость, цена списывается.
     *
     * @return void
     */
    public function testStrikeManeuverAppliesUnstableAfterStrike(): void
    {
        $world = $this->worldWithWeapon(
            extraCards: $this->strikeManeuverCards(),
            extraRules: $this->strikeManeuverRules(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [], abilities: [
            ['ruleCode' => 'strike-maneuver', 'level' => 1],
            ['ruleCode' => 'mastery', 'level' => 1, 'domainCode' => 'melee'],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $battle = $this->openStrikeBattle($gameId, $attackerId, $defenderId, 'm-strike');
        $battleId = $battle['battleId'];
        $closed = $this->closeStrike($gameId, $battleId, $battle['version'], $defenderId, 'm-strike');
        self::assertGreaterThanOrEqual(3, $closed['data']['success']);

        $beforeAp = $this->resourceCurrent($attackerId, 'action-points');
        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'm-maneuver',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'actionRuleCode' => 'strike-maneuver',
                'followUpTargetKey' => 'character:' . $defenderId,
            ]),
        ]);
        self::assertTrue($action['success'], json_encode($action));
        self::assertSame(1, $action['data']['spent']);
        self::assertSame($beforeAp - 1, $this->resourceCurrent($attackerId, 'action-points'));
        self::assertSame(1, $this->stateValue($this->statesOf($defenderId), 'unstable'));
        self::assertFalse($action['data']['strikeManeuver']['boosted'] ?? true);
        $pending = $this->pendingTypes($battleId, 'character:' . $attackerId);
        self::assertNotContains('last_strike_snapshot', $pending);
    }

    /**
     * «Вскрыть слабость» заменяет Неустойчивость 1 на 2 при высоком РУ.
     *
     * Мастерство оружия прошлого удара проверяется: без нужного уровня
     * усиление не срабатывает.
     *
     * @return void
     */
    public function testStrikeManeuverBoostReplacesUnstable(): void
    {
        $world = $this->worldWithWeapon(
            extraCards: $this->strikeManeuverCards(),
            extraRules: $this->strikeManeuverRules(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Boost', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [], abilities: [
            ['ruleCode' => 'strike-maneuver', 'level' => 1],
            ['ruleCode' => 'maneuver-boost', 'level' => 1],
            ['ruleCode' => 'mastery', 'level' => 2, 'domainCode' => 'melee'],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Boost-Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $battle = $this->openStrikeBattle($gameId, $attackerId, $defenderId, 'm-boost');
        $battleId = $battle['battleId'];
        $closed = $this->closeStrike($gameId, $battleId, $battle['version'], $defenderId, 'm-boost');
        self::assertGreaterThanOrEqual(4, $closed['data']['success']);

        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'm-boost-action',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'actionRuleCode' => 'strike-maneuver',
                'followUpTargetKey' => 'character:' . $defenderId,
            ]),
        ]);
        self::assertTrue($action['success'], json_encode($action));
        self::assertTrue($action['data']['strikeManeuver']['boosted'] ?? false);
        self::assertSame(2, $this->stateValue($this->statesOf($defenderId), 'unstable'));
    }

    /**
     * Усиление смотрит на мастерство оружия прошлого удара.
     *
     * При мастерстве ниже 5 «Вскрыть слабость» не срабатывает: остаётся
     * базовый эффект «Разбить защиту».
     *
     * @return void
     */
    public function testStrikeManeuverBoostRequiresWeaponMastery(): void
    {
        $world = $this->worldWithWeapon(
            extraCards: $this->strikeManeuverCards(),
            extraRules: $this->strikeManeuverRules(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Low-Mastery', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [], abilities: [
            ['ruleCode' => 'strike-maneuver', 'level' => 1],
            ['ruleCode' => 'maneuver-boost', 'level' => 1],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Low-Mastery-Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $battle = $this->openStrikeBattle($gameId, $attackerId, $defenderId, 'm-low-mastery');
        $battleId = $battle['battleId'];
        $closed = $this->closeStrike($gameId, $battleId, $battle['version'], $defenderId, 'm-low-mastery');
        self::assertGreaterThanOrEqual(4, $closed['data']['success']);

        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'm-low-mastery-action',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'actionRuleCode' => 'strike-maneuver',
                'followUpTargetKey' => 'character:' . $defenderId,
            ]),
        ]);
        self::assertTrue($action['success'], json_encode($action));
        self::assertFalse($action['data']['strikeManeuver']['boosted'] ?? true);
        self::assertSame(1, $this->stateValue($this->statesOf($defenderId), 'unstable'));
    }

    /**
     * Манёвр без прошлого удара отклоняется.
     *
     * @return void
     */
    public function testStrikeManeuverRequiresPreviousStrike(): void
    {
        $world = $this->worldWithWeapon(
            extraCards: $this->strikeManeuverCards(),
            extraRules: $this->strikeManeuverRules(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Lonely', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [], abilities: [
            ['ruleCode' => 'strike-maneuver', 'level' => 1],
            ['ruleCode' => 'mastery', 'level' => 1, 'domainCode' => 'melee'],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Lonely-Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'm-lonely-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'm-lonely-action',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'actionRuleCode' => 'strike-maneuver',
                'followUpTargetKey' => 'character:' . $defenderId,
            ]),
        ]);
        self::assertSame('GAME_INVALID', $action['error']['code'] ?? null, json_encode($action));
    }

    /**
     * Манёвр требует РУ не ниже порога.
     *
     * @return void
     */
    public function testStrikeManeuverRequiresThreshold(): void
    {
        $cards = $this->strikeManeuverCards();
        $cards['strike-maneuver']['spec']['strike_maneuver']['min_success'] = 99;
        $world = $this->worldWithWeapon(
            extraCards: $cards,
            extraRules: $this->strikeManeuverRules(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Weak', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [], abilities: [
            ['ruleCode' => 'strike-maneuver', 'level' => 1],
            ['ruleCode' => 'mastery', 'level' => 1, 'domainCode' => 'melee'],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Weak-Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $battle = $this->openStrikeBattle($gameId, $attackerId, $defenderId, 'm-weak');
        $battleId = $battle['battleId'];
        $closed = $this->closeStrike($gameId, $battleId, $battle['version'], $defenderId, 'm-weak');
        self::assertGreaterThanOrEqual(3, $closed['data']['success']);

        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'm-weak-action',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'actionRuleCode' => 'strike-maneuver',
                'followUpTargetKey' => 'character:' . $defenderId,
            ]),
        ]);
        self::assertSame('GAME_INVALID', $action['error']['code'] ?? null, json_encode($action));
        self::assertSame(0, $this->stateValue($this->statesOf($defenderId), 'unstable') ?? 0);
    }

    /**
     * Манёвр не применяется к цели, которой не было в прошлом ударе.
     *
     * @return void
     */
    public function testStrikeManeuverRejectsWrongTarget(): void
    {
        $world = $this->worldWithWeapon(
            extraCards: $this->strikeManeuverCards(),
            extraRules: $this->strikeManeuverRules(),
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Maneuver-Wrong', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [], abilities: [
            ['ruleCode' => 'strike-maneuver', 'level' => 1],
            ['ruleCode' => 'mastery', 'level' => 1, 'domainCode' => 'melee'],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Maneuver-Wrong-Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $otherId = $this->admit($world->getId(), $gameId, 'Maneuver-Other', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $battle = $this->openStrikeBattle($gameId, $attackerId, $defenderId, 'm-wrong', [$otherId]);
        $battleId = $battle['battleId'];
        $closed = $this->closeStrike($gameId, $battleId, $battle['version'], $defenderId, 'm-wrong');

        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'm-wrong-action',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'actionRuleCode' => 'strike-maneuver',
                'followUpTargetKey' => 'character:' . $otherId,
            ]),
        ]);
        self::assertSame('GAME_INVALID', $action['error']['code'] ?? null, json_encode($action));
    }

    /**
     * Открывает бой и объявляет обычный удар.
     *
     * @param int $gameId Игра.
     * @param int $attackerId Атакующий.
     * @param int $defenderId Цель.
     * @param string $key Ключ сценария.
     * @param list<int> $extra Участники сверх пары.
     *
     * @return array{battleId: int, version: int} Бой и версия после объявления.
     */
    private function openStrikeBattle(
        int $gameId,
        int $attackerId,
        int $defenderId,
        string $key,
        array $extra = [],
    ): array {
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $participants = [
            ['type' => 'character', 'id' => $attackerId],
            ['type' => 'character', 'id' => $defenderId],
        ];
        foreach ($extra as $id) {
            $participants[] = ['type' => 'character', 'id' => $id];
        }
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => $key . '-battle',
            'participants' => $participants,
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key . '-strike',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($declared['success'], json_encode($declared));

        return [
            'battleId' => $battleId,
            'version' => $declared['data']['version'],
        ];
    }

    /**
     * Закрывает удар выбранной реакцией цели.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param int $version Версия боя после объявления.
     * @param int $defenderId Цель.
     * @param string $key Ключ сценария.
     *
     * @return array<string, mixed> Итог.
     */
    private function closeStrike(
        int $gameId,
        int $battleId,
        int $version,
        int $defenderId,
        string $key,
    ): array {
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key . '-close',
            'expectedVersion' => $version,
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        return $closed;
    }

    /**
     * Карточка манёвра после удара с типизированным эффектом.
     *
     * @return array<string, array<string, mixed>> Карточки.
     */
    private function strikeManeuverCards(): array
    {
        return [
            'strike-maneuver' => [
                'components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 1,
                ]],
                'effects' => [],
                'spec' => [
                    'strike_maneuver' => [
                        'min_success' => 3,
                        'min_weapon_mastery' => 3,
                        'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 1]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Состояние неустойчивости и усиление манёвра.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function strikeManeuverRules(): array
    {
        return [
            'action-points' => [
                'type' => 'resource',
                'name' => 'Action points',
                'spec' => [
                    'is_dimensional' => false,
                    'auto_add' => true,
                    'check_token' => false,
                    'turn_refill' => true,
                    'limit' => ['base' => 20, 'adjustments' => []],
                ],
            ],
            'unstable' => [
                'type' => 'state',
                'name' => 'Неустойчивость',
                'spec' => ['value_type' => 'number', 'aggregation' => 'sum', 'unstable' => true],
            ],
            'mastery' => [
                'type' => 'ability',
                'name' => 'Владение оружием',
                'spec' => [
                    'type' => 'skill',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1, 1, 1, 1, 1]]],
                    'multiple' => true,
                    'domain_ref' => 'weapon-family',
                    'requirements' => [],
                ],
            ],
            'maneuver-boost' => [
                'type' => 'ability',
                'name' => 'Вскрыть слабость',
                'spec' => [
                    'type' => 'skill',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                    'maneuver_boost' => [
                        'maneuvers' => ['strike-maneuver'],
                        'min_success' => 4,
                        'min_weapon_mastery' => 5,
                        'replaces' => [['kind' => 'state', 'state' => 'unstable']],
                        'effects' => [['kind' => 'state', 'state' => 'unstable', 'amount' => 2]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Значение состояния листа.
     *
     * @param array<int, array<string, mixed>> $states Состояния.
     * @param string $code Код состояния.
     *
     * @return int|null Значение или null.
     */
    private function stateValue(array $states, string $code): ?int
    {
        foreach ($states as $row) {
            if (is_array($row) && ($row['stateRuleCode'] ?? null) === $code) {
                return is_int($row['value'] ?? null) ? $row['value'] : null;
            }
        }

        return null;
    }

    /**
     * Текущее значение ресурса листа.
     *
     * @param int $characterId Персонаж.
     * @param string $code Код ресурса.
     *
     * @return int Значение.
     */
    private function resourceCurrent(int $characterId, string $code): int
    {
        $resources = $this->characterFacade()->get($characterId)->getSheet()['resources'] ?? [];
        foreach ($resources as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === $code) {
                return is_int($row['current'] ?? null) ? $row['current'] : 0;
            }
        }

        return 0;
    }

    private function maneuverRules(): array
    {
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'joint',
            'ordinary_root' => true,
            'concentration_token' => false,
            'willpower' => false,
            'unstable_check' => false,
            'hit_check' => false,
            'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
        ];

        return [
            'deception' => [
                'type' => 'check',
                'name' => 'Deception',
                'spec' => [...$check, 'characteristic_code' => 'will'],
                'roll' => true,
            ],
            'check-attention' => [
                'type' => 'check',
                'name' => 'Attention',
                'spec' => [...$check, 'characteristic_code' => 'will'],
                'roll' => true,
            ],
            'check-melee-combat' => [
                'type' => 'check',
                'name' => 'Melee combat',
                'spec' => [...$check, 'characteristic_code' => 'strength'],
                'roll' => true,
            ],
            'obmannyy-manevr' => [
                'type' => 'ability',
                'name' => 'Обманный манёвр',
                'spec' => [
                    'type' => 'skill',
                    'maneuver' => [
                        'trigger' => 'strike',
                        'window' => 'after_defense_before_check',
                        'attack' => 'auto_fail',
                        'check' => [
                            'attacker' => [
                                ['check' => 'deception', 'characteristic' => 'will'],
                            ],
                            'target' => [
                                ['check' => 'check-attention', 'characteristic' => 'will'],
                                ['check' => 'check-melee-combat', 'characteristic' => 'strength'],
                            ],
                        ],
                        'outcome' => [
                            'on_target_passed' => 'may_ignore',
                            'on_target_failed' => [[
                                'kind' => 'advantage',
                                'value' => -2,
                                'source_code' => 'circumstances',
                                'scope' => 'defense_against_attacker',
                                'consumes' => 'first_defense',
                                'expires' => 'attacker_turn',
                            ]],
                        ],
                    ],
                ],
            ],
        ];
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

        $blocked = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-blocked',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'critical')),
        ]);
        self::assertFalse($blocked['success']);
        self::assertSame('GAME_INVALID', $blocked['error']['code']);

        $first = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-first',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-follow-up-first',
            'expectedVersion' => $first['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
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

        $wrongTarget = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-wrong',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $otherId, 'critical')),
        ]);
        self::assertFalse($wrongTarget['success']);
        self::assertSame('GAME_INVALID', $wrongTarget['error']['code']);

        $second = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-follow-up-second',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'critical')),
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
        $first = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lethal-first',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-lethal-first',
            'expectedVersion' => $first['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $previousSr = $closed['data']['success'];
        self::assertGreaterThan(0, $previousSr);

        $second = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lethal-second',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'lethal')),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        $closedLethal = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-lethal-second',
            'expectedVersion' => $second['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-action',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-action',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $beforeAp = $this->actionPointsOf($attackerId);
        $sheetBefore = $this->characterFacade()->get($attackerId)->getActualVersion();
        $action = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                    'expectedSheetVersion' => $sheetBefore,
                    'actor' => ['type' => 'character', 'id' => $attackerId],
                    'actionRuleCode' => 'counter',
                    'followUpTargetKey' => 'character:' . $defenderId,
                ]),
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

        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->actionEnvelope([
                    'expectedSheetVersion' => $sheetBefore,
                    'actor' => ['type' => 'character', 'id' => $attackerId],
                    'actionRuleCode' => 'counter',
                    'followUpTargetKey' => 'character:' . $defenderId,
                ]),
        ]);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertSame($beforeAp - 1, $this->actionPointsOf($attackerId));
        $missing = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action-missing',
            'expectedVersion' => $action['data']['version'],
            'action' => $this->actionEnvelope([
                    'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                    'actor' => ['type' => 'character', 'id' => $attackerId],
                    'actionRuleCode' => 'counter',
                    'followUpTargetKey' => 'character:' . $defenderId,
                ]),
        ]);
        self::assertFalse($missing['success']);
        self::assertSame('GAME_INVALID', $missing['error']['code'] ?? null);

        $apBeforeWait = $this->actionPointsOf($attackerId);
        self::assertSame($beforeAp - 1, $apBeforeWait);
        $wait = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-action-wait',
            'expectedVersion' => $action['data']['version'],
            'action' => $this->actionEnvelope([
                    'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                    'actor' => ['type' => 'character', 'id' => $attackerId],
                    'actionRuleCode' => 'wait',
                    'followUpTargetKey' => null,
                    'chosenAmounts' => ['action-points' => 3],
                ]),
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
     * Действие пишет состояния: лечь, встать, снять неустойчивость.
     *
     * @return void
     */
    public function testActionWritesStatesAndSuppressesUnstable(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->stateActionRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $heroId = $this->admit($world->getId(), $gameId, 'Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-states',
            'participants' => [['type' => 'character', 'id' => $heroId]],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $version = 1;

        $version = $this->runStateAction($gameId, $battleId, $version, 'u-1', $heroId, 'apply-unstable');
        self::assertEquals([['stateRuleCode' => 'unstable', 'value' => 2, 'id' => 1]], $this->statesOf($heroId));
        self::assertSame(19, $this->actionPointsOf($heroId));

        $version = $this->runStateAction($gameId, $battleId, $version, 's-1', $heroId, 'stand-up');
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));
        self::assertSame(16, $this->actionPointsOf($heroId));

        $version = $this->runStateAction($gameId, $battleId, $version, 'u-2', $heroId, 'apply-unstable');
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));
        self::assertSame(15, $this->actionPointsOf($heroId));

        $version = $this->runStateAction($gameId, $battleId, $version, 'r-1', $heroId, 'recover-stability');
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));
        self::assertSame(13, $this->actionPointsOf($heroId));

        $version = $this->runStateAction($gameId, $battleId, $version, 's-2', $heroId, 'stand-up');
        self::assertEquals([], $this->statesOf($heroId));
        self::assertSame(10, $this->actionPointsOf($heroId));

        $version = $this->runStateAction($gameId, $battleId, $version, 'u-3', $heroId, 'apply-unstable');
        self::assertEquals([['stateRuleCode' => 'unstable', 'value' => 2, 'id' => 1]], $this->statesOf($heroId));
        self::assertSame(9, $this->actionPointsOf($heroId));
    }

    /**
     * Прирост Неустойчивости бросает проверку; провал кладёт лежачее.
     *
     * @return void
     */
    public function testActionIncreaseCheckFallsAndSuppressesUnstable(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->increaseCheckRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $heroId = $this->admit($world->getId(), $gameId, 'Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-increase',
            'participants' => [['type' => 'character', 'id' => $heroId]],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $first = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'u-1',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($heroId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $heroId],
                'actionRuleCode' => 'apply-unstable',
                'followUpTargetKey' => null,
            ]),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        self::assertCount(1, $first['data']['rolls'] ?? []);
        $roll = $first['data']['rolls'][0]['roll'];
        self::assertIsArray($roll);
        self::assertLessThanOrEqual(0, $roll['rating'] ?? 1);
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));

        $turn = $this->messagesByThreadKind($gameId, 'turn');
        self::assertNotEmpty($this->attachmentsOfType($turn, 'roll'));

        $second = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'u-2',
            'expectedVersion' => $first['data']['version'],
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($heroId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $heroId],
                'actionRuleCode' => 'apply-unstable',
                'followUpTargetKey' => null,
            ]),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        self::assertSame([], $second['data']['rolls'] ?? []);
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));
    }

    /**
     * Ручная правка ГМ идёт тем же правилом прироста.
     *
     * @return void
     */
    public function testManualIncreaseCheckFallsAndSuppressesUnstable(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->increaseCheckRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $heroId = $this->admit($world->getId(), $gameId, 'Hero');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);

        $added = $this->dispatch('game.manualSheet', [
            'gameId' => $gameId,
            'idempotencyKey' => 'm-1',
            'operation' => 'addState',
            'target' => ['kind' => 'character', 'id' => $heroId],
            'expectedSheetVersion' => $this->characterFacade()->get($heroId)->getActualVersion(),
            'details' => ['stateRuleCode' => 'unstable', 'value' => 2],
        ]);
        self::assertTrue($added['success'], json_encode($added));
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));
        $session = $this->messagesByThreadKind($gameId, 'session');
        self::assertNotEmpty($this->attachmentsOfType($session, 'roll'));

        $again = $this->dispatch('game.manualSheet', [
            'gameId' => $gameId,
            'idempotencyKey' => 'm-2',
            'operation' => 'addState',
            'target' => ['kind' => 'character', 'id' => $heroId],
            'expectedSheetVersion' => $added['data']['actualVersion'],
            'details' => ['stateRuleCode' => 'unstable', 'value' => 2],
        ]);
        self::assertTrue($again['success'], json_encode($again));
        self::assertEquals([['stateRuleCode' => 'lying', 'id' => 1]], $this->statesOf($heroId));
    }

    /**
     * Правила мира с проверкой прироста Неустойчивости.
     *
     * @return array<string, array<string, mixed>> Правила мира.
     */
    private function increaseCheckRules(): array
    {
        $rules = $this->stateActionRules();
        $rules['lying']['spec']['suppressed_state_codes'] = ['unstable'];
        $rules['unstable']['spec']['increase_check_code'] = 'check-unstable';
        $rules['check-unstable'] = [
            'type' => 'check',
            'name' => 'Increase check',
            'spec' => [
                'allowed_modes' => 'solo',
                'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
                'outcomes' => [['max_rating' => 0, 'state_rule_code' => 'lying']],
            ],
        ];

        return $rules;
    }

    /**
     * Состояния и действия сценария.
     *
     * @return array<string, array<string, mixed>> Правила мира.
     */
    private function stateActionRules(): array
    {
        return [
            'action-points' => [
                'type' => 'resource',
                'name' => 'Action points',
                'spec' => [
                    'is_dimensional' => false,
                    'auto_add' => true,
                    'check_token' => false,
                    'turn_refill' => true,
                    'limit' => ['base' => 20, 'adjustments' => []],
                ],
            ],
            'lying' => [
                'type' => 'state',
                'name' => 'Lying',
                'spec' => [
                    'value_type' => 'flag',
                    'aggregation' => 'sum',
                    'lying' => true,
                    'suppressed_state_codes' => ['unstable'],
                ],
            ],
            'dexterity' => [
                'type' => 'characteristic',
                'spec' => ['automatic' => ['value' => ['base' => 20, 'size' => 0]]],
            ],
            'unstable' => [
                'type' => 'state',
                'name' => 'Unstable',
                'spec' => ['value_type' => 'number', 'aggregation' => 'sum', 'unstable' => true],
            ],
            'stand-up' => [
                'type' => 'ability',
                'name' => 'Stand up',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 3,
                    ]],
                    'operations' => [['type' => 'posture', 'posture' => 'standing_or_prone']],
                ],
            ],
            'recover-stability' => [
                'type' => 'ability',
                'name' => 'Recover stability',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'combat_action' => 'recover-stability',
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 2,
                    ]],
                ],
            ],
            'apply-unstable' => [
                'type' => 'ability',
                'name' => 'Apply unstable',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 1,
                    ]],
                    'action_effects' => [[
                        'type' => 'apply_state',
                        'state_code' => 'unstable',
                        'amount' => 2,
                    ]],
                ],
            ],
        ];
    }

    /**
     * Выполняет действие и возвращает версию боя из ответа.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param int $version Версия боя.
     * @param string $key Ключ идемпотентности.
     * @param int $heroId Персонаж.
     * @param string $code Карточка.
     *
     * @return int Версия боя.
     */
    private function runStateAction(
        int $gameId,
        int $battleId,
        int $version,
        string $key,
        int $heroId,
        string $code,
    ): int {
        $response = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => $key,
            'expectedVersion' => $version,
            'action' => $this->actionEnvelope([
                'expectedSheetVersion' => $this->characterFacade()->get($heroId)->getActualVersion(),
                'actor' => ['type' => 'character', 'id' => $heroId],
                'actionRuleCode' => $code,
                'followUpTargetKey' => null,
            ]),
        ]);
        self::assertTrue($response['success'], json_encode($response));

        return $response['data']['version'];
    }

    /**
     * Строки состояний персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return array<int, mixed> Строки.
     */
    private function statesOf(int $characterId): array
    {
        return $this->characterFacade()->get($characterId)->getSheet()['states'] ?? [];
    }

    /**
     * Толчок руками: Сила против пола, урон, Неустойчивость и отброс.
     *
     * @return void
     */
    public function testPushIgnoreUsesStrengthAndAddsUnstable(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->pushRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Pusher', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 4, 'size' => 0]],
        ], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'hands', 'equipped' => true],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'hands', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-push',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'simple-push', 'hands')),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-close',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(17, $this->actionPointsOf($attackerId));
        $success = $closed['data']['success'] ?? 0;
        self::assertGreaterThan(0, $success);
        self::assertSame($success, $closed['data']['appliedSr'] ?? null);
        $amounts = new GameStrikeAmounts();
        $damage = $closed['data']['damage'];
        $resistance = $closed['data']['resistance'];
        $expectedInjury = $amounts->injury(
            new DimensionalNumber($damage['base'], $damage['size']),
            new DimensionalNumber($resistance['base'], $resistance['size']),
            $success,
            null,
        );
        self::assertSame($amounts->view($expectedInjury), $closed['data']['injury']);
        self::assertSame($this->pair(4, -2), $damage);
        $calc = $this->attachmentsOfType($this->chatMessages($gameId), 'attack-calc')[0];
        self::assertSame($this->pair(4, -2), $calc['damage']);
        self::assertSame((string) $success, $calc['attackSrLabel']);
        self::assertIsString($calc['pushSrLabel']);
        self::assertIsArray($calc['stability']);
        $states = $this->statesOf($defenderId);
        $unstable = array_values(array_filter(
            $states,
            static fn (mixed $row): bool => is_array($row) && ($row['stateRuleCode'] ?? null) === 'unstable',
        ));
        self::assertCount(1, $unstable);
        self::assertSame($calc['unstable'], $unstable[0]['value']);
        $contents = implode("\n", array_map(
            static fn (MessageRecord $message): string => $message->getContent(),
            $this->chatMessages($gameId),
        ));
        self::assertStringContainsString('руками', $contents);
        if (is_array($calc['knockback'] ?? null)) {
            self::assertStringContainsString('отбрасывается на', $contents);
        }
    }

    /**
     * Блок толчка — совместная Сила руками, а не блок-профиль оружия.
     *
     * @return void
     */
    public function testPushBlockUsesJointStrengthAndHands(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->pushRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Pusher', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 4, 'size' => 0]],
        ], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'hands', 'equipped' => true],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'hands', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-push-block',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-block-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'simple-push', 'hands')),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-block-close',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope([
                'reaction' => 'block',
                'blockItemInventoryId' => 1,
                'blockItemProfileIndex' => 0,
                'blockItemRuleCode' => 'hands',
            ], $this->characterFacade()->get($defenderId)->getActualVersion()),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertNotNull($closed['data']['strikes'][0]['defenseRoll'] ?? null);
        self::assertSame(18, $this->actionPointsOf($defenderId));
    }

    /**
     * Толчок не принимает оружие без щита.
     *
     * @return void
     */
    public function testPushRejectsWeaponProfile(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->pushRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Pusher', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-push-weapon',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-weapon',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'simple-push', 'sword')),
        ]);
        self::assertSame('GAME_INVALID', $opened['error']['code']);
    }

    /**
     * Толчок щитом допустим.
     *
     * @return void
     */
    public function testPushAllowsShieldProfile(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->pushRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Pusher', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'push-shield', 'equipped' => true],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-push-shield',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-shield',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'simple-push', 'push-shield')),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
    }

    /**
     * Чат толчка с уклонением: проверка Силы до итога, проверка Неустойчивости после.
     *
     * @return void
     */
    public function testPushDodgeChatOrder(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->pushRules(3, -1));
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Pusher', [
            ['characteristicCode' => 'strength', 'value' => ['base' => 4, 'size' => 0]],
        ], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'hands', 'equipped' => true],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['ruleCode' => 'hands', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-push-chat',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-chat-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'simple-push', 'hands')),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'push-chat-close',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $messages = $this->chatMessages($gameId);
        $contents = array_map(static fn (MessageRecord $message): string => $message->getContent(), $messages);
        $result = $this->messageIndexOf($contents, 'получает Unstable');
        $unstable = $this->messageIndexOf($contents, 'проходит проверку');
        $verdict = $this->messageIndexOf($contents, 'падает!');

        self::assertTrue(
            $result < $unstable && $unstable < $verdict,
            json_encode($contents, JSON_UNESCAPED_UNICODE),
        );
        self::assertStringContainsString('совершает действие', $contents[0]);
        self::assertStringContainsString('толкает', $contents[1]);
        self::assertSame(['roll', 'roll'], array_column($this->contentAttachments($messages[2]), 'type'));
        self::assertSame('attack-calc', $this->contentAttachments($messages[$result])[0]['type']);
        self::assertSame('roll', $this->contentAttachments($messages[$unstable])[0]['type']);
        $calc = $this->attachmentsOfType($messages, 'attack-calc')[0];
        self::assertSame('5', $calc['pushSrLabel']);
        self::assertSame((string) $closed['data']['success'], $calc['attackSrLabel']);
        $amounts = new GameStrikeAmounts();
        $soak = is_array($calc['dodgeSoak'] ?? null)
            ? new DimensionalNumber($calc['dodgeSoak']['base'], $calc['dodgeSoak']['size'])
            : null;
        $expected = $amounts->injury(
            new DimensionalNumber($calc['damage']['base'], $calc['damage']['size']),
            new DimensionalNumber($calc['resistance'], 0),
            (int) $calc['attackSrLabel'],
            $soak,
        );
        self::assertSame($amounts->view($expected), $calc['injury']);
        self::assertStringNotContainsString('Сила (толчок)', implode("\n", $contents));
        self::assertStringContainsString('отбрасывает на 1 ипари', $contents[$result]);
        self::assertStringContainsString('Unstable', $contents[$result]);
        self::assertStringContainsString('падает!', $contents[$verdict]);
    }

    /**
     * Индекс сообщения с подстрокой.
     *
     * @param array<int, string> $contents Тексты.
     * @param string $needle Подстрока.
     *
     * @return int Индекс.
     */
    private function messageIndexOf(array $contents, string $needle): int
    {
        foreach ($contents as $index => $content) {
            if (str_contains($content, $needle)) {
                return $index;
            }
        }

        self::fail('Message with "' . $needle . '" was not found: ' . json_encode($contents, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Толкающий удар: пул урона оружия, урон профиля × РУ и половинная поза.
     *
     * @return void
     */
    public function testWeaponPushUsesWeaponDamageAndHalvesPosture(): void
    {
        $world = $this->worldWithWeapon(
            extraRules: $this->pushRules(),
            profileOverrides: [
                'damage' => [
                    'damage_type_code' => 'slashing',
                    'formula' => ['type' => 'fixed', 'value' => 6],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Slasher', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Target', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['id' => 1, 'ruleCode' => 'slash-mail', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'weapon-push-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'weapon-push-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'tolkayuschiy-udar', 'sword'),
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'weapon-push-close',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $success = $closed['data']['success'] ?? 0;
        $unstable = $closed['data']['push']['unstable'] ?? 0;
        self::assertGreaterThan(0, $success);
        self::assertSame(16, $this->actionPointsOf($attackerId));
        self::assertSame($this->pair(6), $closed['data']['damage']);
        self::assertSame($this->pair(5), $closed['data']['resistance']);
        self::assertSame($success, $closed['data']['appliedSr']);
        self::assertSame($this->pair($success), $closed['data']['injury']);
        self::assertGreaterThan(0, $unstable);
        self::assertSame(
            $unstable >= 4 ? $this->pair(intdiv($unstable, 4)) : null,
            $closed['data']['push']['knockback'],
        );

        $messages = $this->chatMessages($gameId);
        $contents = array_map(static fn (MessageRecord $message): string => $message->getContent(), $messages);
        self::assertStringContainsString('получает Unstable ' . $unstable, implode("\n", $contents));
        self::assertStringContainsString('оружием «Sword»', implode("\n", $contents));
        if ($unstable >= 4) {
            self::assertStringContainsString('отбрасывается на', implode("\n", $contents));
        }
    }

    /**
     * Успешный блок оставляет контакт 1 РУ и добавляет защиту блока.
     *
     * @return void
     */
    public function testWeaponPushBlockKeepsContactDamage(): void
    {
        $world = $this->worldWithWeapon(
            extraRules: $this->pushRules(),
            profileOverrides: [
                'damage' => [
                    'damage_type_code' => 'slashing',
                    'formula' => ['type' => 'fixed', 'value' => 2],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Pusher', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Blocker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 20]],
        ], [
            ['id' => 1, 'ruleCode' => 'sword', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'weapon-push-block-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'weapon-push-block-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'tolkayuschiy-udar', 'sword'),
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'weapon-push-block-close',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope([
                'reaction' => 'block',
                'blockItemInventoryId' => 1,
                'blockItemProfileIndex' => 0,
                'blockItemRuleCode' => 'buckler',
            ], $this->characterFacade()->get($defenderId)->getActualVersion()),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        self::assertSame(1, $closed['data']['success'], json_encode($closed['data'], JSON_UNESCAPED_UNICODE));
        self::assertSame(1, $closed['data']['appliedSr']);
        self::assertSame($this->pair(2), $closed['data']['damage']);
        self::assertSame($this->pair(1), $closed['data']['resistance']);
        self::assertSame($this->pair(1), $closed['data']['injury']);
        self::assertSame(0, $closed['data']['push']['unstable']);
        self::assertNull($closed['data']['push']['knockback']);
        self::assertTrue($closed['data']['strikes'][0]['defenseRoll']['success'] ?? false);
        self::assertSame(16, $this->actionPointsOf($attackerId));
        self::assertSame(18, $this->actionPointsOf($defenderId));
        $contents = implode("\n", array_map(
            static fn (MessageRecord $message): string => $message->getContent(),
            $this->chatMessages($gameId),
        ));
        self::assertStringContainsString('контакт 1 РУ', $contents);
        self::assertStringNotContainsString('не толкает', $contents);
    }

    /**
     * Мир с Толчком, Силой, весом и блоком руками.
     *
     * @param int $dexterityBase Ловкость для проверки Неустойчивости.
     * @param int $weightSize Размер веса цели.
     *
     * @return array<string, array<string, mixed>> Правила мира.
     */
    private function pushRules(int $dexterityBase = 20, int $weightSize = 0): array
    {
        $profile = [
            'type' => 'strike',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
            ],
            'accuracy' => ['base' => 3, 'size' => 0],
            'damage' => [
                'damage_type_code' => 'crush',
                'formula' => [
                    'type' => 'actionCharacteristic',
                    'action' => 'strike',
                    'characteristic' => 'strength',
                    'modifier' => [['delta' => -3, 'source_code' => null, 'source_label' => null]],
                ],
            ],
        ];

        return [
            'action-points' => [
                'type' => 'resource',
                'spec' => [
                    'is_dimensional' => false,
                    'auto_add' => true,
                    'check_token' => false,
                    'turn_refill' => true,
                    'limit' => ['base' => 20, 'adjustments' => []],
                ],
            ],
            'weight' => [
                'type' => 'characteristic',
                'spec' => ['automatic' => ['value' => ['base' => 3, 'size' => $weightSize]]],
            ],
            'check-strength' => [
                'type' => 'check',
                'roll' => true,
                'spec' => [
                    'allowed_modes' => 'both',
                    'characteristic_code' => 'strength',
                    'difficulty_input' => ['kind' => 'ask'],
                ],
            ],
            'check-push' => [
                'type' => 'check',
                'roll' => true,
                'spec' => [
                    'allowed_modes' => 'both',
                    'difficulty_input' => ['kind' => 'ask'],
                    'push_check' => true,
                ],
            ],
            'dexterity' => [
                'type' => 'characteristic',
                'spec' => ['automatic' => ['value' => ['base' => $dexterityBase, 'size' => 0]]],
            ],
            'unstable' => [
                'type' => 'state',
                'spec' => [
                    'value_type' => 'number',
                    'aggregation' => 'sum',
                    'unstable' => true,
                    'increase_check_code' => 'check-unstable',
                ],
            ],
            'lying' => [
                'type' => 'state',
                'spec' => [
                    'value_type' => 'flag',
                    'aggregation' => 'sum',
                    'lying' => true,
                    'suppressed_state_codes' => ['unstable'],
                ],
            ],
            'check-unstable' => [
                'type' => 'check',
                'roll' => true,
                'spec' => [
                    'allowed_modes' => 'solo',
                    'characteristic_code' => 'dexterity',
                    'difficulty_input' => ['kind' => 'from_state', 'state_code' => 'unstable'],
                    'outcomes' => [['max_rating' => 0, 'state_rule_code' => 'lying']],
                ],
            ],
            'block' => [
                'type' => 'ability',
                'spec' => [
                    'type' => 'action',
                    'combat_action' => 'block',
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 2,
                    ]],
                ],
            ],
            'hands' => [
                'type' => 'item',
                'spec' => [
                    'innate' => true,
                    'category' => 'weapon',
                    'proficiency_family_code' => 'melee',
                    'weapon' => ['weapon_profiles' => [$profile]],
                    'block_profile' => [
                        'efficiency' => ['base' => 5, 'size' => 0],
                        'defense' => ['base' => 1, 'size' => 0],
                        'resistances' => [],
                    ],
                ],
            ],
            'push-shield' => [
                'type' => 'item',
                'spec' => [
                    'category' => 'shield',
                    'shield' => ['weapon_profiles' => [$profile]],
                    'block_profile' => [
                        'efficiency' => ['base' => 3, 'size' => 0],
                        'defense' => ['base' => 1, 'size' => 0],
                        'resistances' => [],
                    ],
                ],
            ],
            'slash-mail' => [
                'type' => 'item',
                'spec' => [
                    'category' => 'armor',
                    'armor' => [
                        'resistance_slots' => [[
                            'damage_type_code' => 'slashing',
                            'value' => ['base' => 5, 'size' => 0],
                            'durability' => null,
                        ]],
                    ],
                ],
            ],
            'simple-push' => [
                'type' => 'ability',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 3,
                    ]],
                    'push' => [
                        'check' => 'check-push',
                        'profiles' => 'hands_or_shield',
                        'block_profiles' => 'hands_or_shield',
                        'damage_modifier' => -3,
                        'stability_bonus_cap' => 6,
                    ],
                ],
            ],
            'tolkayuschiy-udar' => [
                'type' => 'ability',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 4,
                    ]],
                    'push' => [
                        'check' => 'check-push',
                        'profiles' => 'slashing_or_blunt_strike',
                        'block_profiles' => 'slashing_or_blunt_strike',
                        'damage_modifier' => -1,
                        'stability_bonus_cap' => 6,
                        'posture_rating_divisor_by_damage_type' => ['slashing' => 2],
                    ],
                ],
            ],
        ];
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'chat-single-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ])['success']);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'chat-single-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-owner',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $this->setActor($attackerOwner, []);
        $wrong = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-wrong',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
        ]);
        self::assertSame('AUTH_DENIED', $wrong['error']['code']);
        $this->setActor($defenderOwner, []);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-owner',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $this->setActor($defenderOwner, []);
        $again = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-again',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
        ]);
        self::assertSame('GAME_INVALID', $again['error']['code']);
        $this->setActor($stranger, []);
        $foreign = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-foreign',
            'expectedVersion' => 2,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 's-npc-defender',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId'])),
        ])['success']);
        $this->setActor($player, []);
        $npcDefense = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 'd-npc-defender',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $npc['data']['actualVersion']),
        ]);
        self::assertSame('AUTH_DENIED', $npcDefense['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $defenderBattle['data']['battleId'],
            'idempotencyKey' => 'd-npc-defender',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $npc['data']['actualVersion']),
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
        $npcAttack = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $attackerBattle['data']['battleId'],
            'idempotencyKey' => 's-npc-attacker',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'npc', 'id' => $attackerNpc['data']['npcId']],
                'defender' => ['type' => 'character', 'id' => $characterId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ]);
        self::assertSame('AUTH_DENIED', $npcAttack['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $attackerBattle['data']['battleId'],
            'idempotencyKey' => 's-npc-attacker',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'npc', 'id' => $attackerNpc['data']['npcId']],
                'defender' => ['type' => 'character', 'id' => $characterId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ])['success']);
        $this->setActor($player, []);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($card['success'], json_encode($card));
        self::assertSame($attackerNpc['data']['npcId'], $card['data']['battle']['openStrike']['attackerId'] ?? null);
        self::assertSame($characterId, $card['data']['battle']['openStrike']['defenderId'] ?? null);
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $attackerBattle['data']['battleId'],
            'idempotencyKey' => 'd-npc-attacker',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $characterVersion),
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
                'pay_sr_vs_reliability',
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'concurrent-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId'])),
        ])['success']);
        $payload = [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'concurrent-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $npcVersion),
        ];
        $deliveryBefore = $this->countAll(GameDeliveryTable::class);
        $commandsBefore = $this->countCommands('strike');

        $results = $this->runConcurrentStrikeRequests('game.runAction', $payload);
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

        $sameBodyReplay = $this->dispatch('game.runAction', $payload);
        self::assertTrue($sameBodyReplay['success']);
        self::assertEquals($results[0]['data'], $sameBodyReplay['data']);

        $differentBody = $payload;
        $differentBody['action']['context']['defenses'][0]['reaction'] = 'dodge';
        $differentBodyResult = $this->dispatch('game.runAction', $differentBody);
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'concurrent-owner-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ])['success']);
        $commandsBefore = $this->countCommands('strike');
        $writerPayload = [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'concurrent-owner-writer',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
        ];
        $ownerPayload = $writerPayload;
        $ownerPayload['idempotencyKey'] = 'concurrent-owner-defender';

        $results = $this->runConcurrentStrikeRequests('game.runAction', $writerPayload, [
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($attack),
        ])['success']);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope([
                'reaction' => 'block',
                'blockItemInventoryId' => 1,
                'blockItemProfileIndex' => 0,
                'blockItemRuleCode' => 'sword',
            ], $npc['data']['actualVersion']),
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
     * Потолок РУ типа урона режет повреждение, но не рейтинг попадания.
     *
     * @return void
     */
    public function testDamageTypeSuccessRatingCapLimitsInjury(): void
    {
        $world = $this->worldWithWeapon(
            profileOverrides: [
                'damage' => [
                    'damage_type_code' => 'capped',
                    'formula' => ['type' => 'fixed', 'value' => 4],
                ],
            ],
            extraRules: [
                'capped' => [
                    'type' => 'damage_type',
                    'name' => 'Capped',
                    'spec' => [
                        'forms' => ['genitive' => 'capped', 'dative' => 'capped'],
                        'defense_ignored' => false,
                        'modifies_spell_difficulty' => false,
                        'max_success_rating' => 2,
                    ],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Hero', [[
            'characteristicCode' => 'strength',
            'cost' => 2,
            'value' => ['base' => 4, 'size' => 0],
        ]], [], [['ruleCode' => 'sword', 'equipped' => true]]);
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId'])),
        ])['success']);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $npc['data']['actualVersion'],
            ),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(8, $closed['data']['success']);
        self::assertSame($this->pair(4), $closed['data']['damage']);
        self::assertSame($this->pair(0), $closed['data']['resistance']);
        self::assertSame(2, $closed['data']['appliedSr']);
        self::assertSame(2, $closed['data']['srCap']);
        self::assertSame($this->pair(8), $closed['data']['injury']);

        $calc = $this->attachmentsOfType($this->chatMessages($gameId), 'attack-calc');
        self::assertSame('8', $calc[0]['attackSrLabel'] ?? null);
        self::assertSame(2, $calc[0]['appliedSr'] ?? null);
        self::assertSame(2, $calc[0]['srCap'] ?? null);
    }

    /**
     * Удержание двумя руками даёт +2 к Силе урона поверх карточки.
     *
     * @return void
     */
    public function testGripBonusAddsStrengthToActionDamage(): void
    {
        $oneHand = $this->worldWithWeapon(withActionDamage: true);
        $twoHands = $this->worldWithWeapon(
            withActionDamage: true,
            occupyHands: ['min' => 2, 'max' => 2],
        );

        $plain = $this->resolveDefense($oneHand->getId(), [], 'sword');
        $gripped = $this->resolveDefense($twoHands->getId(), [], 'sword');

        self::assertSame($this->pair(4), $plain['damage']);
        self::assertSame($this->pair(3, 1), $gripped['damage']);
    }

    /**
     * Яростный рывок бьёт по атакующему и снимает помеху родителя только на успехе.
     *
     * @return void
     */
    public function testModifierRunsAndSkipsParentPending(): void
    {
        $setup = $this->modifierBattle(3);
        $opened = $this->declareModifier($setup);
        self::assertTrue($opened['success'], json_encode($opened));
        $beforeClose = $this->characterFacade()->get($setup['attackerId'])->getActualVersion();
        $closed = $this->closeModifier($setup, $opened);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(['rush'], $closed['data']['modifiers']['codes'] ?? null);
        self::assertTrue($closed['data']['modifiers']['success'] ?? null, json_encode($closed));
        self::assertTrue($closed['data']['modifiers']['skipped'] ?? null, json_encode($closed));
        self::assertSame($beforeClose + 1, $this->characterFacade()->get($setup['attackerId'])->getActualVersion());
        self::assertNotContains(
            'after_action_until_resource_spent_check_modifier',
            $this->pendingTypes($setup['battleId'], 'character:' . $setup['attackerId']),
        );
    }

    /**
     * Провал проверки рывка оставляет помеху родителя и не пишет лист.
     *
     * @return void
     */
    public function testModifierFailureKeepsParentPending(): void
    {
        $setup = $this->modifierBattle(99);
        $opened = $this->declareModifier($setup);
        self::assertTrue($opened['success'], json_encode($opened));
        $beforeClose = $this->characterFacade()->get($setup['attackerId'])->getActualVersion();
        $closed = $this->closeModifier($setup, $opened);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertFalse($closed['data']['modifiers']['success'] ?? null);
        self::assertFalse($closed['data']['modifiers']['skipped'] ?? null);
        self::assertArrayNotHasKey('injury', $closed['data']['modifiers'] ?? []);
        self::assertSame($beforeClose, $this->characterFacade()->get($setup['attackerId'])->getActualVersion());
        self::assertContains(
            'after_action_until_resource_spent_check_modifier',
            $this->pendingTypes($setup['battleId'], 'character:' . $setup['attackerId']),
        );
    }

    /**
     * Своя броня уменьшает урон по себе, когда защита не игнорируется.
     *
     * @return void
     */
    public function testSelfDamageAppliesOwnArmorWithoutIgnoreDefense(): void
    {
        $ignored = $this->selfDamageArmorBattle(true, false, 3, false);
        $applied = $this->selfDamageArmorBattle(false, false, 3, false);

        self::assertSame(
            $this->selfDamageInjury($this->closeSelfDamageArmor($ignored)) - 3,
            $this->selfDamageInjury($this->closeSelfDamageArmor($applied)),
        );
    }

    /**
     * Внутренний урон не считает непомеченную броню.
     *
     * @return void
     */
    public function testInternalSelfDamageSkipsUnmarkedArmor(): void
    {
        $plain = $this->selfDamageArmorBattle(true, false, 3, false);
        $internal = $this->selfDamageArmorBattle(true, true, 3, false);

        self::assertSame(
            $this->selfDamageInjury($this->closeSelfDamageArmor($plain)),
            $this->selfDamageInjury($this->closeSelfDamageArmor($internal)),
        );
    }

    /**
     * Помеченная броня держит внутренний урон, а рана становится внутренней.
     *
     * @return void
     */
    public function testInternalSelfDamageUsesMarkedArmorAndWritesInternalWound(): void
    {
        $plain = $this->selfDamageArmorBattle(true, false, 1, true);
        $internal = $this->selfDamageArmorBattle(true, true, 1, true);
        $closed = $this->closeSelfDamageArmor($internal);

        self::assertSame(
            $this->selfDamageInjury($this->closeSelfDamageArmor($plain)) - 1,
            $this->selfDamageInjury($closed),
        );
        $wounds = array_values(array_filter(
            $this->statesOf($internal['attackerId']),
            static fn (mixed $row): bool => is_array($row) && ($row['stateRuleCode'] ?? null) === 'self-wound',
        ));
        self::assertCount(1, $wounds);
        self::assertTrue($wounds[0]['internal'] ?? null);
    }

    /**
     * База урона по себе из итога модификатора.
     *
     * @param array<string, mixed> $closed Итог закрытия.
     *
     * @return int База.
     */
    private function selfDamageInjury(array $closed): int
    {
        $injury = $closed['data']['modifiers']['injuries'][0]['injury'] ?? null;
        self::assertIsArray($injury, json_encode($closed));

        return is_int($injury['base'] ?? null) ? $injury['base'] : 0;
    }

    /**
     * Стенд рывка со своей бронёй на типе урона модификатора.
     *
     * @param bool $ignoreDefense Игнорировать защиту.
     * @param bool $internal Внутренний урон.
     * @param int $resistance Сопротивление брони.
     * @param bool $protectsInternal Держит ли броня внутренний урон.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, defenderId: int} Стенд.
     */
    private function selfDamageArmorBattle(
        bool $ignoreDefense,
        bool $internal,
        int $resistance,
        bool $protectsInternal,
    ): array {
        $world = $this->worldWithWeapon(
            extraRules: [
                'self-wound' => [
                    'type' => 'state',
                    'name' => 'Wound',
                    'spec' => ['value_type' => 'number', 'aggregation' => 'sum'],
                ],
                'self-cut' => [
                    'type' => 'damage_type',
                    'name' => 'Self cut',
                    'spec' => [
                        'forms' => ['genitive' => 'self cut', 'dative' => 'self cut'],
                        'defense_ignored' => false,
                        'modifies_spell_difficulty' => false,
                    ],
                    'mechanics' => [[
                        'mechanic_id' => $this->damageAsWoundsId(),
                        'mechanic_payload' => ['stateRuleCode' => 'self-wound'],
                    ]],
                ],
                'self-armor' => [
                    'type' => 'item',
                    'name' => 'Self armor',
                    'spec' => [
                        'category' => 'armor',
                        'armor' => [
                            'resistance_slots' => [[
                                'damage_type_code' => 'self-cut',
                                'value' => ['base' => $resistance, 'size' => 0],
                                'durability' => null,
                                'protects_internal' => $protectsInternal,
                            ]],
                        ],
                    ],
                ],
            ],
            extraCards: [
                'rush' => [
                    'type' => 'skill',
                    'parent' => 'swing',
                    'effects' => [[
                        'type' => 'action_modifier',
                        'trigger' => 'after_strike',
                        'scope' => ['components' => ['strike'], 'hit_count' => 1],
                        'effects' => [[
                            'type' => 'self_damage',
                            'size_delta' => 0,
                            'damage_type_code' => 'self-cut',
                            'ignore_defense' => $ignoreDefense,
                            'internal' => $internal,
                        ]],
                    ]],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Self Rusher',
            [['characteristicCode' => 'strength', 'value' => ['base' => 5, 'size' => 0]]],
            [],
            [
                ['ruleCode' => 'sword', 'equipped' => true],
                ['ruleCode' => 'self-armor', 'equipped' => true],
            ],
            null,
            null,
            [['ruleCode' => 'rush', 'level' => 1]],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Self Guard', sheet: ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-self-armor',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'attackerId' => $attackerId,
            'defenderId' => $defenderId,
        ];
    }

    /**
     * Объявляет и закрывает удар со своей бронёй.
     *
     * @param array{gameId: int, battleId: int, attackerId: int, defenderId: int} $setup Стенд.
     *
     * @return array<string, mixed> Итог закрытия.
     */
    private function closeSelfDamageArmor(array $setup): array
    {
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 's-self-armor',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($setup['attackerId'], $setup['defenderId'])
                    + ['modifiers' => ['rush']],
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 'd-self-armor',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($setup['defenderId'])->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        return $closed;
    }

    /**
     * Id маркера «урон становится раной».
     *
     * @return int Id.
     */
    private function damageAsWoundsId(): int
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        try {
            return $mechanics->add('damage_as_wounds_by_size', 'Wounds', '', '1.0.0');
        } catch (MechanicInvalidException) {
            return $mechanics->getByCodeVersion('damage_as_wounds_by_size', '1.0.0')->getId();
        }
    }

    /**
     * Список опций отдаёт сервер и только для купленной способности.
     *
     * @return void
     */
    public function testActionOptionsAreServerComputed(): void
    {
        $setup = $this->modifierBattle(3);
        $own = $this->dispatch('game.getActionOptions', [
            'gameId' => $setup['gameId'],
            'actorType' => 'character',
            'actorId' => $setup['attackerId'],
        ]);
        self::assertTrue($own['success'], json_encode($own));
        self::assertSame('swing', $own['data']['modifiers'][0]['actionCode'] ?? null);
        self::assertSame('rush', $own['data']['modifiers'][0]['code'] ?? null);
        self::assertStringContainsString('Willpower', $own['data']['modifiers'][0]['summary'] ?? '');
        $foreign = $this->dispatch('game.getActionOptions', [
            'gameId' => $setup['gameId'],
            'actorType' => 'character',
            'actorId' => $setup['defenderId'],
        ]);
        self::assertTrue($foreign['success'], json_encode($foreign));
        self::assertSame([], $foreign['data']['modifiers']);
    }

    /**
     * Мир и бой с объявленной опцией рывка.
     *
     * @param int $difficulty Сложность проверки.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, defenderId: int, actual: int} Стенд.
     */
    private function modifierBattle(int $difficulty): array
    {
        $world = $this->worldWithWeapon(
            withActionDamage: true,
            cardEffects: [[
                'type' => 'after_action_until_resource_spent_check_modifier',
                'resource_code' => 'action-points',
                'amount' => 2,
                'check_codes' => ['hit'],
                'delta' => -2,
                'source_code' => 'action',
            ]],
            extraCards: [
                'rush' => [
                    'type' => 'skill',
                    'parent' => 'swing',
                    'effects' => [[
                        'type' => 'action_modifier',
                        'trigger' => 'after_strike',
                        'scope' => ['components' => ['strike'], 'hit_count' => 1],
                        'check' => ['code' => 'willpower-check', 'difficulty' => $difficulty],
                        'effects' => [
                            [
                                'type' => 'self_damage',
                                'size_delta' => -1,
                                'damage_type_code' => 'cut',
                                'ignore_defense' => true,
                            ],
                            ['type' => 'skip_after_action'],
                        ],
                    ]],
                ],
            ],
            extraRules: [
                'willpower-check' => [
                    'type' => 'check',
                    'name' => 'Willpower',
                    'roll' => true,
                    'spec' => [
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
                    ],
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Rusher',
            [['characteristicCode' => 'will', 'value' => ['base' => 3, 'size' => 0]]],
            [],
            [],
            null,
            null,
            [['ruleCode' => 'rush', 'level' => 1]],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-rush',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'attackerId' => $attackerId,
            'defenderId' => $defenderId,
            'actual' => $this->characterFacade()->get($attackerId)->getActualVersion(),
        ];
    }

    /**
     * Объявляет удар с опцией рывка.
     *
     * @param array{gameId: int, battleId: int, attackerId: int, defenderId: int, actual: int} $setup Стенд.
     *
     * @return array<string, mixed> Итог.
     */
    private function declareModifier(array $setup): array
    {
        return $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 's-rush',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($setup['attackerId'], $setup['defenderId'])
                    + ['modifiers' => ['rush']],
            ),
        ]);
    }

    /**
     * Закрывает удар с опцией рывка.
     *
     * @param array{gameId: int, battleId: int, attackerId: int, defenderId: int, actual: int} $setup Стенд.
     * @param array<string, mixed> $opened Объявление.
     *
     * @return array<string, mixed> Итог.
     */
    private function closeModifier(array $setup, array $opened): array
    {
        return $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 'd-rush',
            'expectedVersion' => $opened['data']['version'] ?? 1,
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($setup['defenderId'])->getActualVersion(),
            ),
        ]);
    }

    /**
     * Серия исполняет модификатор один раз после всех ударов.
     *
     * @return void
     */
    public function testSeriesModifierRunsOnceForAllStrikes(): void
    {
        $setup = $this->seriesModifierBattle('strongest');
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 's-series-rush',
            'expectedVersion' => 1,
            'action' => $this->sameWeaponEnvelope('double', $setup['attackerId'], $setup['defenderId'], 2, ['rush']),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertCount(2, $opened['data']['strikes']);
        $beforeClose = $this->characterFacade()->get($setup['attackerId'])->getActualVersion();
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 'd-series-rush',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($setup['defenderId'])->getActualVersion(),
            ),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(['rush'], $closed['data']['modifiers']['codes'] ?? null);
        self::assertCount(1, $closed['data']['modifiers']['injuries'] ?? []);
        self::assertSame($beforeClose + 1, $this->characterFacade()->get($setup['attackerId'])->getActualVersion());
        self::assertNotContains(
            'after_action_until_resource_spent_check_modifier',
            $this->pendingTypes($setup['battleId'], 'character:' . $setup['attackerId']),
        );
    }

    /**
     * Режим each в серии наносит урон за каждый удар.
     *
     * @return void
     */
    public function testSeriesModifierEachDamagesEveryStrike(): void
    {
        $setup = $this->seriesModifierBattle('each');
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 's-series-each',
            'expectedVersion' => 1,
            'action' => $this->sameWeaponEnvelope('double', $setup['attackerId'], $setup['defenderId'], 2, ['rush']),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $beforeClose = $this->characterFacade()->get($setup['attackerId'])->getActualVersion();
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 'd-series-each',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($setup['defenderId'])->getActualVersion(),
            ),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertCount(2, $closed['data']['modifiers']['injuries'] ?? []);
        self::assertSame($beforeClose + 2, $this->characterFacade()->get($setup['attackerId'])->getActualVersion());
    }

    /**
     * Разные наборы модификаторов в строках серии отвергаются.
     *
     * @return void
     */
    public function testSeriesRejectsDifferentModifierSets(): void
    {
        $setup = $this->seriesModifierBattle();
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 's-series-diff',
            'expectedVersion' => 1,
            'action' => [
                'phase' => 'declare',
                'kind' => 'strike',
                'code' => 'double',
                'actor' => ['type' => 'character', 'id' => $setup['attackerId']],
                'context' => [
                    'strikes' => [
                        [
                            'itemInventoryId' => 1,
                            'itemRuleCode' => 'sword',
                            'profileType' => 'strike',
                            'profileIndex' => 0,
                            'instanceIndex' => 0,
                            'target' => ['type' => 'character', 'id' => $setup['defenderId']],
                            'modifiers' => ['rush'],
                        ],
                        [
                            'itemInventoryId' => 1,
                            'itemRuleCode' => 'sword',
                            'profileType' => 'strike',
                            'profileIndex' => 0,
                            'instanceIndex' => 1,
                            'target' => ['type' => 'character', 'id' => $setup['defenderId']],
                        ],
                    ],
                    'chosenAmounts' => [],
                ],
            ],
        ]);

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
        self::assertSame(0, $this->countActions('strike'));
    }

    /**
     * Модификатор вне области строк серии отвергается на объявлении.
     *
     * @return void
     */
    public function testSeriesRejectsModifierOutsideScope(): void
    {
        $setup = $this->seriesModifierBattle(scope: ['shoot']);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $setup['gameId'],
            'battleId' => $setup['battleId'],
            'idempotencyKey' => 's-series-scope',
            'expectedVersion' => 1,
            'action' => $this->sameWeaponEnvelope('double', $setup['attackerId'], $setup['defenderId'], 2, ['rush']),
        ]);

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
        self::assertSame(0, $this->countActions('strike'));
    }

    /**
     * Стенд серии с модификатором действия.
     *
     * @param string $mode Режим урона по себе.
     * @param list<string> $scope Профили области.
     *
     * @return array{gameId: int, battleId: int, attackerId: int, defenderId: int} Стенд.
     */
    private function seriesModifierBattle(string $mode = 'strongest', array $scope = ['strike']): array
    {
        $world = $this->worldWithWeapon(
            withPendingEffects: true,
            extraRules: [
                'double' => [
                    'type' => 'ability',
                    'name' => 'Double',
                    'spec' => [
                        'type' => 'action',
                        'zones' => ['or' => ['kind' => 'automatic']],
                        'same_weapon' => true,
                        'min_weapons' => 2,
                        'max_weapons' => 2,
                        'action_components' => [],
                    ],
                ],
            ],
            extraCards: [
                'rush' => [
                    'type' => 'skill',
                    'parent' => 'double',
                    'effects' => [[
                        'type' => 'action_modifier',
                        'trigger' => 'after_strike',
                        'scope' => ['components' => $scope, 'hit_count' => 1],
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
                ],
            ],
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Series Rusher',
            sheet: ['resources' => [['ruleCode' => 'action-points', 'current' => 10]]],
            inventory: [['ruleCode' => 'sword', 'equipped' => true, 'quantity' => 2]],
            abilities: [['ruleCode' => 'rush', 'level' => 1]],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Series Guard', sheet: ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-series-' . $mode . md5(implode(',', $scope)),
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));

        return [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'attackerId' => $attackerId,
            'defenderId' => $defenderId,
        ];
    }

    /**
     * Опция, которой у персонажа нет, отвергается уже на объявлении.
     *
     * @return void
     */
    public function testStrikeRejectsForeignModifierAtDeclare(): void
    {
        $world = $this->worldWithWeapon(extraCards: [
            'rush' => [
                'type' => 'skill',
                'parent' => 'swing',
                'effects' => [[
                    'type' => 'action_modifier',
                    'trigger' => 'after_strike',
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                    'check' => ['code' => 'will-check', 'difficulty' => 3],
                    'effects' => [
                        [
                            'type' => 'self_damage',
                            'size_delta' => -1,
                            'damage_type_code' => 'cut',
                            'ignore_defense' => true,
                        ],
                        ['type' => 'skip_after_action'],
                    ],
                ]],
            ],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Plain');
        $defenderId = $this->admit($world->getId(), $gameId, 'Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-reject',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-reject',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId) + ['modifiers' => ['rush']],
            ),
        ]);
        self::assertSame('GAME_INVALID', $opened['error']['code']);
        self::assertSame(0, $this->countActions('strike'));
    }

    /**
     * Один источник оставляет сильнейший бонус, включая бонус от РУ.
     *
     * @return void
     */
    public function testSameSourceCharacteristicModifiersCollapse(): void
    {
        $static = [
            'type' => 'current_action_attack_characteristic_modifier',
            'delta' => 2,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
            'source_code' => 'action',
        ];
        $stronger = [
            'type' => 'current_action_attack_characteristic_modifier',
            'delta' => 3,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
            'source_code' => 'action',
        ];
        $rating = $this->successRatingEffect() + ['source_code' => 'action'];

        $collapsed = $this->resolveActionDamage([$static, $stronger]);
        $withRating = $this->resolveActionDamage([$static, $stronger, $rating]);

        self::assertSame($this->pair(4, 1), $collapsed['damage']);
        self::assertSame($this->pair(4, 1), $withRating['damage']);
    }

    /**
     * Бонус Силы от рейтинга успеха добавляется к урону и упирается в потолок.
     *
     * @return void
     */
    public function testSuccessRatingCharacteristicModifierRaisesActionDamage(): void
    {
        $plain = $this->resolveActionDamage([]);
        $boosted = $this->resolveActionDamage([$this->successRatingEffect()]);
        self::assertSame(8, $plain['success']);
        self::assertSame(8, $boosted['success']);
        self::assertSame($this->pair(4), $plain['damage']);
        self::assertSame($this->pair(4, 1), $boosted['damage']);
    }

    /**
     * Бонус от РУ складывается со сдвигом характеристики карточки.
     *
     * @return void
     */
    public function testSuccessRatingBonusStacksWithActionCharacteristicModifier(): void
    {
        $stacked = $this->resolveActionDamage([
            $this->successRatingEffect(),
            [
                'type' => 'current_action_attack_characteristic_modifier',
                'delta' => 2,
                'scope' => ['components' => ['strike'], 'hit_count' => 1],
                'damage_type_codes' => [],
            ],
        ]);
        self::assertSame($this->pair(3, 2), $stacked['damage']);
    }

    /**
     * База профиля перекрывает характеристику листа и получает пункты карточки.
     *
     * @return void
     */
    public function testProfileActionCharacteristicBeatsCharacterAndCardModifier(): void
    {
        $world = $this->worldWithWeapon(
            withActionDamage: true,
            cardEffects: [[
                'type' => 'current_action_attack_characteristic_modifier',
                'delta' => 2,
                'scope' => ['components' => ['strike'], 'hit_count' => 1],
                'damage_type_codes' => [],
            ]],
            profileOverrides: [
                'damage' => [
                    'damage_type_code' => 'cut',
                    'formula' => [
                        'type' => 'actionCharacteristic',
                        'action' => 'strike',
                        'characteristic' => 'strength',
                        'modifier' => [],
                    ],
                ],
                'action_characteristics' => [[
                    'characteristic' => 'strength',
                    'value' => ['type' => 'dimensional', 'base' => 3, 'size' => 2],
                ]],
            ],
        );

        $closed = $this->resolveDefense($world->getId(), [], 'sword');

        self::assertSame($this->pair(5, 2), $closed['damage']);
    }

    /**
     * База профиля видна в пробитии, а не только в уроне.
     *
     * @return void
     */
    public function testProfileActionCharacteristicFeedsPenetration(): void
    {
        $world = $this->worldWithWeapon(
            withPenetration: true,
            profileOverrides: [
                'penetration' => [
                    'type' => 'actionCharacteristic',
                    'action' => 'strike',
                    'characteristic' => 'strength',
                    'modifier' => [],
                ],
                'action_characteristics' => [[
                    'characteristic' => 'strength',
                    'value' => ['type' => 'dimensional', 'base' => 3, 'size' => 0],
                ]],
            ],
        );

        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'sword',
            true,
            'ignore',
            false,
            [['id' => 1, 'ruleCode' => 'plate', 'equipped' => true]],
        );

        self::assertSame($this->pair(6), $closed['damage']);
        self::assertSame($this->pair(2), $closed['resistance']);
    }

    /**
     * Карточка не у атакующего и без automatic-зоны — отказ.
     *
     * @return void
     */
    public function testStrikeRejectsCardNotOwned(): void
    {
        $opened = $this->declareActionCard('locked', extraCards: [
            'locked' => ['effects' => [], 'zones' => []],
        ]);

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
        self::assertSame(0, $this->countActions('strike'));
    }

    /**
     * Неавтоматическая карточка с уровнем объявляется.
     *
     * @return void
     */
    public function testStrikeAcceptsOwnedCard(): void
    {
        $opened = $this->declareActionCard(
            'locked',
            abilities: [['ruleCode' => 'locked', 'level' => 1]],
            extraCards: ['locked' => ['effects' => [], 'zones' => []]],
        );

        self::assertTrue($opened['success'], json_encode($opened));
    }

    /**
     * automatic-карточка без уровня объявляется.
     *
     * @return void
     */
    public function testStrikeAcceptsAutomaticCardWithoutLevel(): void
    {
        $opened = $this->declareActionCard('free', extraCards: [
            'free' => ['effects' => []],
        ]);

        self::assertTrue($opened['success'], json_encode($opened));
    }

    /**
     * automatic-карточка с невыполненным has_ability — отказ (G2).
     *
     * @return void
     */
    public function testStrikeRejectsAutomaticCardWithUnmetRequirement(): void
    {
        $opened = $this->declareActionCard('gated', extraCards: [
            'gated' => [
                'effects' => [],
                'requirements' => [[
                    'level' => 1,
                    'requirements' => [['type' => 'has_ability', 'ability_code' => 'missing', 'min_level' => 1]],
                ]],
            ],
        ]);

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
    }

    /**
     * Требование карточки выполнено магическим путём — данных нет, отказ.
     *
     * @return void
     */
    public function testStrikeRejectsUnmodelledRequirement(): void
    {
        $opened = $this->declareActionCard('mage-card', extraCards: [
            'mage-card' => [
                'effects' => [],
                'requirements' => [[
                    'level' => 1,
                    'requirements' => [['type' => 'has_magic_path', 'path_code' => 'psionic']],
                ]],
            ],
        ]);

        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null);
    }

    /**
     * Требование удовлетворено производным уровнем из посчитанного листа.
     *
     * @return void
     */
    public function testStrikeAcceptsRequirementMetByDerivedLevel(): void
    {
        $keywords = $this->gameApplication()->getLocator()->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        try {
            $sectionId = $keywords->add('section-melee', 'Ближний бой');
        } catch (KeywordInvalidException) {
            $sectionId = $keywords->getByCode('section-melee')->getId();
        }

        $opened = $this->declareActionCard(
            'power-strike',
            abilities: [
                ['ruleCode' => 'section-blade', 'level' => 1],
                ['ruleCode' => 'power-strike', 'level' => 1],
            ],
            extraCards: [
                'power-strike' => [
                    'effects' => [],
                    'zones' => [],
                    'requirements' => [[
                        'level' => 1,
                        'requirements' => [[
                            'type' => 'has_ability',
                            'ability_code' => 'derived-blade',
                            'min_level' => 1,
                        ]],
                    ]],
                ],
            ],
            extraRules: [
                'section-blade' => [
                    'type' => 'ability',
                    'name' => 'Section blade',
                    'spec' => [
                        'type' => 'skill',
                        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [2]]],
                        'requirements' => [],
                    ],
                    'keywordIds' => [$sectionId],
                ],
                'derived-blade' => [
                    'type' => 'ability',
                    'name' => 'Derived blade',
                    'spec' => [
                        'type' => 'skill',
                        'derived_level' => ['source_keyword' => 'section-melee', 'thresholds' => [2]],
                    ],
                ],
            ],
        );

        self::assertTrue($opened['success'], json_encode($opened));
    }

    /**
     * min_weapon_mastery выполняется уровнем владения семьёй предмета.
     *
     * @return void
     */
    public function testStrikeAcceptsWeaponMasteryRequirement(): void
    {
        $keywords = $this->gameApplication()->getLocator()->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        try {
            $tagId = $keywords->add('tagged-blade', 'Клинок');
        } catch (KeywordInvalidException) {
            $tagId = $keywords->getByCode('tagged-blade')->getId();
        }

        $opened = $this->declareActionCard(
            'mastered',
            abilities: [
                ['ruleCode' => 'mastery', 'level' => 2, 'domainCode' => 'tagged'],
                ['ruleCode' => 'mastered', 'level' => 1],
            ],
            itemRuleCode: 'tagged-sword',
            inventory: [['ruleCode' => 'tagged-sword', 'equipped' => true]],
            extraCards: [
                'mastered' => [
                    'effects' => [],
                    'zones' => [],
                    'requirements' => [[
                        'level' => 1,
                        'requirements' => [[
                            'type' => 'min_weapon_mastery',
                            'keyword_code' => 'tagged-blade',
                            'min_level' => 2,
                        ]],
                    ]],
                ],
            ],
            extraRules: [
                'tagged-sword' => [
                    'type' => 'item',
                    'name' => 'Tagged sword',
                    'spec' => [
                        'category' => 'weapon',
                        'innate' => false,
                        'special_rule_codes' => [],
                        'proficiency_family_code' => 'tagged',
                        'weapon' => ['weapon_profiles' => [[
                            'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                            'accuracy' => ['base' => 3, 'size' => 0],
                        ]]],
                    ],
                    'keywordIds' => [$tagId],
                ],
                'mastery' => [
                    'type' => 'ability',
                    'name' => 'Mastery',
                    'spec' => [
                        'type' => 'skill',
                        'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1, 1, 1]]],
                        'multiple' => true,
                        'domain_ref' => 'weapon-family',
                        'requirements' => [],
                    ],
                ],
            ],
        );

        self::assertTrue($opened['success'], json_encode($opened));
    }

    /**
     * declareAction отвергает карточку, которой нет у актора.
     *
     * @return void
     */
    public function testDeclareActionRejectsCardNotOwned(): void
    {
        $world = $this->worldWithWeapon(extraCards: [
            'locked-action' => ['effects' => [], 'zones' => []],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $actorId = $this->admit($world->getId(), $gameId, 'Actor');
        $otherId = $this->admit($world->getId(), $gameId, 'Other');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-locked-action',
            'participants' => [
                ['type' => 'character', 'id' => $actorId],
                ['type' => 'character', 'id' => $otherId],
            ],
        ]);
        $declared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'a-locked-action',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                    'expectedSheetVersion' => $this->characterFacade()->get($actorId)->getActualVersion(),
                    'actor' => ['type' => 'character', 'id' => $actorId],
                    'actionRuleCode' => 'locked-action',
                    'followUpTargetKey' => null,
                ]),
        ]);

        self::assertSame('GAME_INVALID', $declared['error']['code'] ?? null);
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'single-penetration-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($attack),
        ])['success']);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'single-penetration-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $defenderVersion),
        ]);

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame($this->pair(6), $closed['data']['damage']);
        self::assertSame($this->pair(1), $closed['data']['resistance']);
        self::assertSame($this->pair(36, -1), $closed['data']['injury']);
        self::assertSame(
            ['battleId', 'strikeId', 'version', 'success', 'damage', 'resistance', 'sheetVersion', 'appliedSr', 'srCap', 'S', 'injury', 'wound', 'exhaustionDelta', 'damageRemainder', 'endurance', 'will', 'willOutcome', 'maim', 'exhaustionTotal', 'states', 'closed', 'kind', 'phase', 'strikes', 'pendingStrikes'],
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
     * Смягчение берёт Ловкость из спеки реакции и пользе профиля.
     *
     * Урон {3|0}, сопротивление {1|0}, польза −4, Ловкость {5|0} дают S {4|-1};
     * повреждение вычитает S из (урон − сопротивление) × РУ.
     *
     * @return void
     */
    public function testResolveSoakFromAgilityAndWeaponBenefit(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, false, [], [], null, [
            'soak-sword' => [
                'type' => 'item',
                'name' => 'Soak sword',
                'spec' => [
                    'category' => 'weapon',
                    'proficiency_family_code' => 'melee',
                    'weapon' => ['weapon_profiles' => [[
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
                        'damage' => [
                            'damage_type_code' => 'cut',
                            'formula' => ['type' => 'fixed', 'value' => 3],
                        ],
                        'dodge_benefit' => -4,
                    ]]],
                ],
            ],
            'soak-mail' => [
                'type' => 'item',
                'name' => 'Soak mail',
                'spec' => [
                    'category' => 'armor',
                    'armor' => ['resistance_slots' => [[
                        'damage_type_code' => 'cut',
                        'value' => ['base' => 1, 'size' => 0],
                        'durability' => null,
                    ]]],
                ],
            ],
        ]);
        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'soak-sword',
            true,
            'dodge',
            false,
            [['id' => 1, 'ruleCode' => 'soak-mail', 'equipped' => true]],
            [
                ['characteristicCode' => 'strength', 'cost' => 2],
                ['characteristicCode' => 'agility', 'cost' => 4],
            ],
        );
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(3), $closed['damage']);
        self::assertSame($this->pair(1), $closed['resistance']);
        self::assertSame($this->pair(4, -1), $closed['S']);
        self::assertSame($this->pair(12, -1), $closed['injury']);
    }

    /**
     * Направленный удар режет S на размер шкалы характеристики.
     *
     * @return void
     */
    public function testResolveDodgeSoakCutByCardReducesSize(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, false, [[
            'type' => 'current_action_attack_dodge_soak',
            'size_delta' => -3,
            'ignore_at_sr' => 5,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
        ]]);
        $closed = $this->resolveDefense($world->getId(), [], 'sword', true, 'dodge');
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(4, -2), $closed['S']);
    }

    /**
     * При РУ не ниже ignore_at_sr смягчение обнуляется.
     *
     * @return void
     */
    public function testResolveDodgeSoakCutIgnoresSoakAtRating(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, false, [[
            'type' => 'current_action_attack_dodge_soak',
            'size_delta' => -3,
            'ignore_at_sr' => 3,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
        ]]);
        $closed = $this->resolveDefense($world->getId(), [], 'sword', true, 'dodge');
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(0), $closed['S']);
        self::assertSame($this->pair(0), $closed['injury']);
    }

    /**
     * Чужой компонент профиля эффект не применяет.
     *
     * @return void
     */
    public function testResolveDodgeSoakCutSkipsOtherComponent(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, false, [[
            'type' => 'current_action_attack_dodge_soak',
            'size_delta' => -3,
            'ignore_at_sr' => 3,
            'scope' => ['components' => ['shoot'], 'hit_count' => 1],
        ]]);
        $closed = $this->resolveDefense($world->getId(), [], 'sword', true, 'dodge');
        self::assertSame($this->pair(4, -1), $closed['S']);
    }

    /**
     * Подготовка Стремительного снижает S следующей атаки на Реакцию.
     *
     * @return void
     */
    public function testPreparedDodgeSoakTailReducesSoak(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, true, [], [
            'soak-prep' => [
                'components' => [['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 1]],
                'effects' => [[
                    'type' => 'next_action_attack_dodge_soak_from_reaction',
                    'max_total_action_cost' => 2,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ]],
            ],
        ]);
        $closed = $this->prepareThenStrike($world->getId(), 'soak-prep', 'swing');
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(2), $closed['S']);
    }

    /**
     * Атака дороже потолка ОД снимает хвост без применения.
     *
     * @return void
     */
    public function testPreparedDodgeSoakTailDropsAboveCost(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, true, [], [
            'soak-prep' => [
                'components' => [['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 1]],
                'effects' => [[
                    'type' => 'next_action_attack_dodge_soak_from_reaction',
                    'max_total_action_cost' => 2,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ]],
            ],
            'heavy' => [
                'components' => [['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 3]],
                'effects' => [],
            ],
        ]);
        $closed = $this->prepareThenStrike($world->getId(), 'soak-prep', 'heavy');
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(5), $closed['S']);
    }

    /**
     * Подготовка хвоста и закрытие атаки по цели с Ловкостью {5|0}.
     *
     * @param int $spaceId Мир.
     * @param string $prepCode Карточка подготовки.
     * @param string $attackCode Карточка атаки.
     *
     * @return array<string, mixed> Итог закрытия.
     */
    private function prepareThenStrike(int $spaceId, string $prepCode, string $attackCode): array
    {
        $gameId = $this->addGame($spaceId, $this->worldRevision());
        $attackerId = $this->admit(
            $spaceId,
            $gameId,
            'Attacker',
            [],
            ['resources' => [['ruleCode' => 'action-points', 'current' => 10]]],
            [['ruleCode' => 'wand', 'equipped' => true]],
            null,
            [['characteristicCode' => 'strength', 'cost' => 2]],
        );
        $defenderId = $this->admit(
            $spaceId,
            $gameId,
            'Defender',
            [],
            [],
            [],
            null,
            [
                ['characteristicCode' => 'strength', 'cost' => 2],
                ['characteristicCode' => 'agility', 'cost' => 4],
            ],
        );
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-soak-tail',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $prepared = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'a-soak-prep',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                    'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
                    'actor' => ['type' => 'character', 'id' => $attackerId],
                    'actionRuleCode' => $prepCode,
                    'followUpTargetKey' => null,
                ]),
        ]);
        self::assertTrue($prepared['success'], json_encode($prepared));
        self::assertContains(
            'next_action_attack_dodge_soak_from_reaction',
            $this->pendingTypes($battleId, 'character:' . $attackerId),
        );
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-soak-tail',
            'expectedVersion' => $prepared['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, $attackCode, 'wand')),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-soak-tail',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $this->characterFacade()->get($defenderId)->getActualVersion()),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $soak = is_array($closed['data']['S'] ?? null) ? $closed['data']['S'] : null;
        self::assertIsArray($soak);
        $calc = $this->attachmentsOfType($this->chatMessages($gameId), 'attack-calc');
        self::assertCount(1, $calc);
        self::assertSame($soak, $calc[0]['dodgeSoak'] ?? null);
        self::assertSame($closed['data']['injury'], $calc[0]['injury'] ?? null);
        self::assertSame(0, $calc[0]['durabilityShave'] ?? null);

        return $closed['data'];
    }

    /**
     * Живой pay_sr_vs_reliability типа урона снимает слои с надёжностью не выше РУ.
     *
     * @return void
     */
    public function testReliabilityCutRemovesLayersAtOrBelowRating(): void
    {
        $world = $this->worldWithWeapon(extraRules: [
            'reliability-blade' => [
                'type' => 'item',
                'name' => 'Reliability blade',
                'spec' => [
                    'category' => 'weapon',
                    'proficiency_family_code' => 'melee',
                    'weapon' => ['weapon_profiles' => [[
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
                        'damage' => ['damage_type_code' => 'cutting'],
                    ]]],
                ],
            ],
            'reliability-mail' => [
                'type' => 'item',
                'name' => 'Reliability mail',
                'spec' => [
                    'category' => 'armor',
                    'armor' => ['resistance_slots' => [
                        [
                            'damage_type_code' => 'cutting',
                            'value' => ['base' => 1, 'size' => 0],
                            'durability' => null,
                        ],
                        [
                            'damage_type_code' => 'cutting',
                            'value' => ['base' => 3, 'size' => 0],
                            'durability' => 5,
                        ],
                        [
                            'damage_type_code' => 'cutting',
                            'value' => ['base' => 1, 'size' => 0],
                            'durability' => 3,
                        ],
                        [
                            'damage_type_code' => 'cutting',
                            'value' => ['base' => 1, 'size' => 0],
                            'durability' => 0,
                        ],
                    ]],
                ],
            ],
        ]);
        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'reliability-blade',
            true,
            'dodge',
            false,
            [['id' => 1, 'ruleCode' => 'reliability-mail', 'equipped' => true]],
        );
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(4), $closed['resistance']);
    }

    /**
     * Тип урона без pay_sr_vs_reliability не снимает слой с надёжностью 1.
     *
     * @return void
     */
    public function testDamageTypeWithoutReliabilityCutKeepsLayer(): void
    {
        $world = $this->worldWithWeapon(extraRules: [
            'reliability-blunt' => [
                'type' => 'item',
                'name' => 'Reliability blunt',
                'spec' => [
                    'category' => 'weapon',
                    'proficiency_family_code' => 'melee',
                    'weapon' => ['weapon_profiles' => [[
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
                        'damage' => ['damage_type_code' => 'crush'],
                    ]]],
                ],
            ],
            'reliability-plate' => [
                'type' => 'item',
                'name' => 'Reliability plate',
                'spec' => [
                    'category' => 'armor',
                    'armor' => ['resistance_slots' => [[
                        'damage_type_code' => 'crush',
                        'value' => ['base' => 2, 'size' => 0],
                        'durability' => 1,
                    ]]],
                ],
            ],
        ]);
        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'reliability-blunt',
            true,
            'dodge',
            false,
            [['id' => 1, 'ruleCode' => 'reliability-plate', 'equipped' => true]],
        );
        self::assertSame(4, $closed['success']);
        self::assertSame($this->pair(2), $closed['resistance']);
    }

    /**
     * Удар в сочленение сдвигает порог надёжности на единицы броска.
     *
     * @return void
     */
    public function testDurabilityShaveAddsOneCountToThreshold(): void
    {
        $world = $this->worldWithWeapon(
            cardEffects: [$this->durabilityShaveEffect(false)],
            extraRules: $this->shaveGear(),
        );
        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'shave-blade',
            true,
            'dodge',
            false,
            [['id' => 1, 'ruleCode' => 'shave-mail', 'equipped' => true]],
        );
        self::assertSame(4, $closed['success']);
        self::assertSame(4, $closed['durabilityShave']);
        self::assertSame($this->pair(3), $closed['resistance']);
    }

    /**
     * Короткое оружие добавляет единицу к срезу только при выпавшей единице.
     *
     * @return void
     */
    public function testDurabilityShaveShortWeaponAddsOne(): void
    {
        $keywords = $this->gameApplication()->getLocator()->get(IKeywordContainer::class)->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        try {
            $shortId = $keywords->add('short', 'Короткий');
        } catch (KeywordInvalidException) {
            $shortId = $keywords->getByCode('short')->getId();
        }

        $gear = $this->shaveGear();
        $gear['shave-blade']['keywordIds'] = [$shortId];
        $world = $this->worldWithWeapon(
            cardEffects: [$this->durabilityShaveEffect(true)],
            extraRules: $gear,
        );
        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'shave-blade',
            true,
            'dodge',
            false,
            [['id' => 1, 'ruleCode' => 'shave-mail', 'equipped' => true]],
        );
        self::assertSame(4, $closed['success']);
        self::assertSame(5, $closed['durabilityShave']);
        self::assertSame($this->pair(0), $closed['resistance']);
    }

    /**
     * Без единиц на гранях срез нулевой.
     *
     * @return void
     */
    public function testDurabilityShaveNeedsOneFaces(): void
    {
        $world = $this->worldWithWeapon(
            cardEffects: [
                $this->durabilityShaveEffect(true),
                [
                    'type' => 'current_action_roll_face_remap',
                    'from' => 1,
                    'to' => 2,
                    'scope' => ['components' => ['strike'], 'hit_count' => 1],
                ],
            ],
            extraRules: $this->shaveGear(),
        );
        $closed = $this->resolveDefense(
            $world->getId(),
            [],
            'shave-blade',
            true,
            'dodge',
            false,
            [['id' => 1, 'ruleCode' => 'shave-mail', 'equipped' => true]],
        );
        self::assertSame(4, $closed['success']);
        self::assertArrayNotHasKey('durabilityShave', $closed);
        self::assertSame($this->pair(5), $closed['resistance']);
    }

    /**
     * Эффект среза надёжности.
     *
     * @param bool $shortExtra Короткое оружие.
     *
     * @return array<string, mixed> Эффект.
     */
    private function durabilityShaveEffect(bool $shortExtra): array
    {
        return [
            'type' => 'current_action_durability_shave',
            'short_extra_on_first_one' => $shortExtra,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
            'damage_type_codes' => ['cutting'],
        ];
    }

    /**
     * Оружие и доспех для среза надёжности.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function shaveGear(): array
    {
        return [
            'shave-blade' => [
                'type' => 'item',
                'name' => 'Shave blade',
                'spec' => [
                    'category' => 'weapon',
                    'proficiency_family_code' => 'melee',
                    'weapon' => ['weapon_profiles' => [[
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
                        'damage' => ['damage_type_code' => 'cutting'],
                    ]]],
                ],
            ],
            'shave-mail' => [
                'type' => 'item',
                'name' => 'Shave mail',
                'spec' => [
                    'category' => 'armor',
                    'armor' => ['resistance_slots' => [
                        [
                            'damage_type_code' => 'cutting',
                            'value' => ['base' => 2, 'size' => 0],
                            'durability' => 8,
                        ],
                        [
                            'damage_type_code' => 'cutting',
                            'value' => ['base' => 3, 'size' => 0],
                            'durability' => 9,
                        ],
                    ]],
                ],
            ],
        ];
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
     * Нет dodge_soak в спеке реакции — смягчения нет, удар закрывается.
     *
     * @return void
     */
    public function testResolveDodgeWithoutSoakFieldOmitsSoak(): void
    {
        $world = $this->worldWithWeapon();
        $closed = $this->resolveDefense($world->getId(), [], 'sword', true, 'dodge');
        self::assertArrayNotHasKey('S', $closed);
        self::assertSame($this->pair(0), $closed['injury']);
        self::assertSame(4, $closed['success']);
    }

    /**
     * Сдвоенный удар: два экземпляра одного оружия, один бросок, два пакета.
     *
     * @return void
     */
    public function testSameWeaponStrikeClosesWithTwoPackets(): void
    {
        $world = $this->worldWithWeapon(extraRules: [
            'double' => [
                'type' => 'ability',
                'name' => 'Double',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'same_weapon' => true,
                    'min_weapons' => 2,
                    'max_weapons' => 2,
                    'action_components' => [],
                ],
            ],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Twin Hero',
            inventory: [['ruleCode' => 'sword', 'equipped' => true, 'quantity' => 2]],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Twin Guard', sheet: ['hp' => 100]);
        $defenderVersion = $this->characterFacade()->get($defenderId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'twin-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'twin-open',
            'expectedVersion' => 1,
            'action' => $this->sameWeaponEnvelope('double', $attackerId, $defenderId, 2),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertCount(2, $opened['data']['strikes']);
        self::assertCount(2, $opened['data']['pendingStrikes']);
        self::assertSame(2, $this->countActionStrikes());
        self::assertSame(1, $this->countActions('strike'));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'twin-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertCount(2, $closed['data']['strikes']);
        self::assertSame($closed['data']['strikes'][0]['injury'], $closed['data']['strikes'][1]['injury']);
        self::assertSame($closed['data']['strikes'][0]['success'], $closed['data']['strikes'][1]['success']);
        self::assertSame($defenderVersion + 1, $closed['data']['sheetVersion']);
        self::assertSame([], $closed['data']['pendingStrikes']);
    }

    /**
     * Обоерукая: два удара разным оружием, у каждого свой бросок и реакция.
     *
     * @return void
     */
    public function testDistinctWeaponStrikesCloseOneByOne(): void
    {
        $world = $this->worldWithWeapon(extraRules: [
            'twin' => [
                'type' => 'ability',
                'name' => 'Twin',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'strike_count' => 2,
                    'distinct_weapons' => true,
                    'action_components' => [],
                ],
            ],
        ]);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Dual Hero',
            inventory: [
                ['ruleCode' => 'sword', 'equipped' => true],
                ['ruleCode' => 'blade', 'equipped' => true],
            ],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Dual Guard', sheet: ['hp' => 100]);
        $defenderVersion = $this->characterFacade()->get($defenderId)->getActualVersion();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'dual-battle',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'dual-open',
            'expectedVersion' => 1,
            'action' => [
                'phase' => 'declare',
                'kind' => 'strike',
                'code' => 'twin',
                'actor' => ['type' => 'character', 'id' => $attackerId],
                'context' => [
                    'strikes' => [
                        [
                            'itemInventoryId' => 1,
                            'itemRuleCode' => 'sword',
                            'profileType' => 'strike',
                            'profileIndex' => 0,
                            'target' => ['type' => 'character', 'id' => $defenderId],
                        ],
                        [
                            'itemInventoryId' => 2,
                            'itemRuleCode' => 'blade',
                            'profileType' => 'strike',
                            'profileIndex' => 0,
                            'target' => ['type' => 'character', 'id' => $defenderId],
                        ],
                    ],
                    'chosenAmounts' => [],
                ],
            ],
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertCount(2, $opened['data']['strikes']);
        $first = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'dual-first',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $defenderVersion),
        ]);
        self::assertTrue($first['success'], json_encode($first));
        self::assertFalse($first['data']['closed']);
        self::assertCount(1, $first['data']['pendingStrikes']);
        self::assertNull($first['data']['strikes'][1]['reaction']);
        self::assertArrayNotHasKey('roll', $first['data']['strikes'][1]);
        self::assertSame(3, $first['data']['version']);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($card['success'], json_encode($card));
        self::assertSame('blade', $card['data']['battle']['openStrike']['itemRuleCode'] ?? null);
        $second = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'dual-second',
            'expectedVersion' => $first['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        self::assertTrue($second['data']['closed']);
        self::assertSame([], $second['data']['pendingStrikes']);
        self::assertSame($defenderVersion + 2, $second['data']['sheetVersion']);
        self::assertSame($defenderVersion + 2, $this->characterFacade()->get($defenderId)->getActualVersion());
    }

    /**
     * Потолок экземпляров без навыка отвергает третий удар.
     *
     * @return void
     */
    public function testSameWeaponStrikeRejectsThirdCopyWithoutSkill(): void
    {
        $world = $this->sameWeaponWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Twin Hero',
            inventory: [['ruleCode' => 'sword', 'equipped' => true, 'quantity' => 3]],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Twin Guard', sheet: ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'twin-limit',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $rejected = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'twin-three',
            'expectedVersion' => 1,
            'action' => $this->sameWeaponEnvelope('double', $attackerId, $defenderId, 3),
        ]);
        self::assertSame('GAME_INVALID', $rejected['error']['code']);
        self::assertSame(0, $this->countActions('strike'));
    }

    /**
     * Навык «Оружия много — удар один» снимает потолок.
     *
     * @return void
     */
    public function testSameWeaponSkillLiftsMaxWeapons(): void
    {
        $world = $this->sameWeaponWorld();
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit(
            $world->getId(),
            $gameId,
            'Twin Hero',
            inventory: [['ruleCode' => 'sword', 'equipped' => true, 'quantity' => 3]],
            abilities: [['ruleCode' => 'many', 'level' => 1]],
        );
        $defenderId = $this->admit($world->getId(), $gameId, 'Twin Guard', sheet: ['hp' => 100]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'twin-lift',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'twin-lift-open',
            'expectedVersion' => 1,
            'action' => $this->sameWeaponEnvelope('double', $attackerId, $defenderId, 3),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertCount(3, $opened['data']['strikes']);
        self::assertSame(3, $this->countActionStrikes());
    }

    /**
     * Мир с карточкой Сдвоенного и навыком снятия потолка.
     *
     * @return RuleSpaceRecord Мир.
     */
    private function sameWeaponWorld(): RuleSpaceRecord
    {
        return $this->worldWithWeapon(extraRules: [
            'double' => [
                'type' => 'ability',
                'name' => 'Double',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'same_weapon' => true,
                    'min_weapons' => 2,
                    'max_weapons' => 2,
                    'action_components' => [],
                ],
            ],
            'many' => [
                'type' => 'ability',
                'name' => 'Many weapons',
                'spec' => [
                    'type' => 'skill',
                    'zones' => ['or' => ['kind' => 'array', 'levels_cost' => [1]]],
                    'parent_ability_code' => 'double',
                    'lift_parent_max_weapons' => true,
                ],
            ],
        ]);
    }

    /**
     * Конверт Сдвоенного удара с N экземплярами.
     *
     * @param string $code Карточка.
     * @param int $attackerId Атакующий.
     * @param int $defenderId Цель.
     * @param int $count Экземпляры.
     *
     * @return array<string, mixed> Действие.
     */
    private function sameWeaponEnvelope(string $code, int $attackerId, int $defenderId, int $count, array $modifiers = []): array
    {
        $strikes = [];
        for ($index = 0; $index < $count; ++$index) {
            $strikes[] = [
                'itemInventoryId' => 1,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
                'instanceIndex' => $index,
                'target' => ['type' => 'character', 'id' => $defenderId],
                ...($modifiers === [] ? [] : ['modifiers' => $modifiers]),
            ];
        }

        return [
            'phase' => 'declare',
            'kind' => 'strike',
            'code' => $code,
            'actor' => ['type' => 'character', 'id' => $attackerId],
            'context' => ['strikes' => $strikes, 'chosenAmounts' => []],
        ];
    }

    /**
     * dodge_soak есть, а характеристики в листе нет — GAME_INVALID.
     *
     * @return void
     */
    public function testResolveDodgeSoakMissingCharacteristicFails(): void
    {
        $world = $this->worldWithWeapon(dodgeSoak: 'missing');
        $closed = $this->resolveDefense($world->getId(), [], 'sword', false, 'dodge');
        self::assertSame('GAME_INVALID', $closed['error']['code']);
    }

    /**
     * Одна карточка реакции даёт базовую ловкость и без закупки.
     *
     * @return void
     */
    public function testResolveDodgeUsesBaseAgilityCharacteristic(): void
    {
        $world = $this->worldWithWeapon(1, true, 1);
        $closed = $this->resolveDefense($world->getId(), ['characteristicPurchases' => []], 'sword', true, 'dodge');
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
        $bad = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'bad',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($attack),
        ]);
        self::assertSame('GAME_INVALID', $bad['error']['code']);
        self::assertSame(0, $this->countActions('strike'));
        $damage = $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId']));
        $damage['context']['strikes'][0]['actionPointCost'] = 1;
        $rejected = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'dmg',
            'expectedVersion' => 1,
            'action' => $damage,
        ]);
        self::assertSame('INVALID_PARAMS', $rejected['error']['code']);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId'])),
        ]);
        self::assertTrue($opened['success']);
        $stale = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'stale',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $npc['data']['actualVersion'] + 1),
        ]);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        self::assertArrayHasKey('choices', $stale['error']['details']);
        self::assertArrayHasKey('sheet', $stale['error']['details']);
        self::assertArrayNotHasKey('currentSheet', $stale['error']['details']);
        $staleBattle = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'stale-battle',
            'expectedVersion' => 1,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $npc['data']['actualVersion']),
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-' . $gameId,
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->attack($characterId, $npc['data']['npcId'])),
        ])['success']);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-' . $gameId,
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $npc['data']['actualVersion']),
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'single-cas-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ])['success']);
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $strike = $mysql->select("select s.item_inventory_id, s.item_rule_code, a.open from game_action_strike s join game_action a on a.id = s.action_id where a.kind = 'strike'");
        self::assertSame(2, (int) $strike[0]->item_inventory_id);
        self::assertSame('sword', (string) $strike[0]->item_rule_code);
        $commandsBefore = $this->countCommands('strike');
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, [$defenderId]);
        try {
            $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'single-cas-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'dodge'], $defenderVersion),
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
        self::assertTrue($this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'auto-fail-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ])['success']);
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, []);
        try {
            $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'auto-fail-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope([
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'sword',
                ], $defenderVersion),
            ]);
        } finally {
            $probe->disarm();
        }

        self::assertTrue($closed['success'] ?? false, json_encode($closed));
        self::assertSame(['base' => 0, 'size' => -1], $closed['data']['strikes'][0]['roll']['roll'] ?? null);
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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'resource-ignore-open',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 2,
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $mysql = PenetrationMutationCasProbe::connect($this->gameApplication());
        $probe = new PenetrationMutationCasProbe();
        $probe->arm($mysql, []);
        try {
            $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'resource-ignore-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope([
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'sword',
                ], $defenderVersion),
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
        self::assertArrayHasKey('defenseRoll', $closed['data']['strikes'][0]);
        self::assertSame([], $probe->getUpdates(), json_encode($probe->getSeenSql()));
        self::assertEquals($defenderSheet, $this->characterFacade()->get($defenderId)->getSheet());
        self::assertSame($defenderVersion, $this->characterFacade()->get($defenderId)->getActualVersion());
        self::assertEquals($attackerSheet, $this->characterFacade()->get($attackerId)->getSheet());
        self::assertSame(1, (int) $mysql->select("select open from game_action where kind = 'strike'")[0]->open);
        self::assertSame(2, (int) $mysql->select('select state_version from game_battle where id = ?', [$battleId])[0]->state_version);
        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'resource-ignore-close',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope([
                'reaction' => 'block',
                'blockItemInventoryId' => 1,
                'blockItemProfileIndex' => 0,
                'blockItemRuleCode' => 'sword',
            ], $defenderVersion),
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
     * Мир с оружием и щитом без профиля атаки.
     *
     * @param int $hitCards Сколько карточек попадания.
     * @param bool $withPool Есть ли число кубов и граней.
     * @param int $soakCards Сколько характеристик уклонения.
     * @param bool $withPenetration Живая формула penetration.
     * @param int|null $hitEfficiency Явная эффективность hit-check.
     * @param bool $withBlockResource Дорогая block-реакция focus.
     * @param array<string, mixed> $profileOverrides Поля профиля поверх фикстуры.
     *
     * @return RuleSpaceRecord Мир.
     */
    /**
     * Мир с процессом-действием.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function processActionRules(): array
    {
        return array_merge($this->stateActionRules(), [
            'act' => [
                'type' => 'ability',
                'name' => 'Act',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'action_components' => [
                        ['type' => 'resource', 'resource_code' => 'action-points', 'amount' => 99],
                    ],
                ],
            ],
            'proc' => [
                'type' => 'ability',
                'name' => 'Proc',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'steps' => [[
                            'code' => 'do',
                            'name' => 'Do',
                            'costs' => [['amount' => 2, 'resource_code' => 'action-points']],
                            'execution' => ['type' => 'action', 'action_code' => 'act'],
                            'description' => 'Act once.',
                        ]],
                        'start_step_code' => 'do',
                        'exit_step_codes' => ['do'],
                        'failure' => 'end_action',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Шаг-действие берёт цену шага и завершает процесс.
     *
     * @return void
     */
    public function testProcessActionUsesStepCostAndResolves(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processActionRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit($world->getId(), $gameId, 'Action Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 5]],
        ], abilities: [
            ['ruleCode' => 'act', 'level' => 1],
            ['ruleCode' => 'proc', 'level' => 1],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-proc-action',
            'participants' => [['type' => 'character', 'id' => $characterId]],
        ]);
        $battleId = $battle['data']['battleId'];
        $sheetVersion = $this->characterFacade()->get($characterId)->getActualVersion();
        $result = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-proc-action',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                'actor' => ['type' => 'character', 'id' => $characterId],
                'actionRuleCode' => 'proc',
                'expectedSheetVersion' => $sheetVersion,
                'followUpTargetKey' => null,
                'processRuleCode' => 'proc',
            ]),
        ]);
        self::assertTrue($result['success'], json_encode($result));
        self::assertSame(3, $this->actionPointsOf($characterId));
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = (new GameProcessRepository($gateway))->findOpenForActor($battleId, 'character', $characterId);
        self::assertNull($row);
    }

    /**
     * Мир с процессом без исполнения (движение).
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function processDescriptiveRules(): array
    {
        return array_merge($this->stateActionRules(), [
            'go' => [
                'type' => 'ability',
                'name' => 'Go',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'steps' => [[
                            'code' => 'go-start',
                            'name' => 'Бег',
                            'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                            'operations' => [[
                                'type' => 'movement',
                                'allowedDirections' => ['horizontal' => ['front'], 'vertical' => []],
                                'distance' => ['horizontal' => [
                                    'min' => ['type' => 'current_movement_step', 'multiplier' => 1],
                                    'max' => [
                                        'type' => 'size_gap_times_step',
                                        'characteristic_code_from' => 'strength',
                                        'characteristic_code_to' => 'weight',
                                        'base_steps' => 2,
                                        'gap_multiplier' => 1,
                                    ],
                                    'minInclusive' => true,
                                    'maxInclusive' => true,
                                ]],
                            ]],
                            'description' => 'Часть бега.',
                        ], [
                            'code' => 'go-continue',
                            'name' => 'Продолжение бега',
                            'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                            'operations' => [],
                            'description' => 'Следующая часть бега.',
                        ]],
                        'start_step_code' => 'go-start',
                        'exit_step_codes' => ['go-continue'],
                        'transition' => ['mode' => 'chain', 'direction' => 'forward', 'max_shift' => 1],
                        'failure' => 'end_action',
                    ],
                ],
            ],
            'rest' => [
                'type' => 'ability',
                'name' => 'Rest',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'steps' => [[
                            'code' => 'rest-only',
                            'name' => 'Отдых',
                            'costs' => [],
                            'operations' => [],
                            'description' => 'Шаг без цены.',
                        ]],
                        'start_step_code' => 'rest-only',
                        'exit_step_codes' => ['rest-only'],
                        'failure' => 'end_action',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Описательный шаг списывает ОД, двигает процесс и пишет чат.
     *
     * @return void
     */
    public function testDescriptiveStepSpendsAndWritesChat(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processDescriptiveRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $heroId = $this->admit($world->getId(), $gameId, 'Runner', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 5]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-descriptive',
            'participants' => [['type' => 'character', 'id' => $heroId]],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $sheetVersion = $this->characterFacade()->get($heroId)->getActualVersion();
        $result = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'go-start',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                'actor' => ['type' => 'character', 'id' => $heroId],
                'actionRuleCode' => 'go',
                'expectedSheetVersion' => $sheetVersion,
                'followUpTargetKey' => null,
                'processRuleCode' => 'go',
            ]),
        ]);
        self::assertTrue($result['success'], json_encode($result));
        self::assertSame(1, $result['data']['spent']);
        self::assertSame(4, $this->actionPointsOf($heroId));
        self::assertSame(0, $this->countActions('action'));

        $replay = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'go-start',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                'actor' => ['type' => 'character', 'id' => $heroId],
                'actionRuleCode' => 'go',
                'expectedSheetVersion' => $sheetVersion,
                'followUpTargetKey' => null,
                'processRuleCode' => 'go',
            ]),
        ]);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertSame(4, $this->actionPointsOf($heroId));

        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $row = (new GameProcessRepository($gateway))->findOpenForActor($battleId, 'character', $heroId);
        self::assertNotNull($row);
        self::assertSame('go-start', $row->getCurrentStepCode());
        self::assertSame('completed', $row->getStepStatus());

        $sheets = $this->dispatch('game.getBattleSheets', ['gameId' => $gameId, 'battleId' => $battleId]);
        $view = $sheets['data']['processSessions']['character:' . $heroId] ?? null;
        self::assertIsArray($view);
        self::assertSame('go-start', $view['currentStepCode']);
        self::assertSame('go-continue', $view['availableSteps'][0]['code'] ?? null);
        self::assertSame('none', $view['availableSteps'][0]['execution'] ?? null);
        self::assertNull($view['availableSteps'][0]['actionCode'] ?? null);

        $messages = array_values(array_filter(
            $this->messagesByThreadKind($gameId, 'turn'),
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'совершает действие'),
        ));
        self::assertCount(1, $messages);
        self::assertStringContainsString('[[rule:go]]', $messages[0]->getContent());
        self::assertStringContainsString('(шаг «Бег»)', $messages[0]->getContent());

        $aborted = $this->dispatch('game.abortProcess', [
            'gameId' => $gameId,
            'idempotencyKey' => 'go-abort',
            'battleId' => $battleId,
            'expectedBattleVersion' => $result['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($heroId)->getActualVersion(),
            'mode' => 'normal',
        ]);
        self::assertTrue($aborted['success'], json_encode($aborted));
        self::assertTrue($aborted['data']['aborted']);
        self::assertSame('go', $aborted['data']['processRuleCode']);
        self::assertNull((new GameProcessRepository($gateway))->findOpenForActor($battleId, 'character', $heroId));
    }

    /**
     * Описательный шаг с нулевой ценой не пишет лист и закрывает процесс.
     *
     * @return void
     */
    public function testDescriptiveStepWithoutCostKeepsSheet(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processDescriptiveRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $heroId = $this->admit($world->getId(), $gameId, 'Rest Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 5]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-rest',
            'participants' => [['type' => 'character', 'id' => $heroId]],
        ]);
        $battleId = $battle['data']['battleId'];
        $sheetVersion = $this->characterFacade()->get($heroId)->getActualVersion();
        $result = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'rest-only',
            'expectedVersion' => 1,
            'action' => $this->actionEnvelope([
                'actor' => ['type' => 'character', 'id' => $heroId],
                'actionRuleCode' => 'rest',
                'expectedSheetVersion' => $sheetVersion,
                'followUpTargetKey' => null,
                'processRuleCode' => 'rest',
            ]),
        ]);
        self::assertTrue($result['success'], json_encode($result));
        self::assertSame(0, $result['data']['spent']);
        self::assertSame(5, $this->actionPointsOf($heroId));
        self::assertSame($sheetVersion, $this->characterFacade()->get($heroId)->getActualVersion());
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        self::assertNull((new GameProcessRepository($gateway))->findOpenForActor($battleId, 'character', $heroId));
    }

    /**
     * Шаг без исполнения нельзя объявить ударом.
     *
     * @return void
     */
    public function testDescriptiveStepRejectsStrike(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processDescriptiveRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Not Striker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-descriptive-strike',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'descriptive-strike',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'go') + ['processRuleCode' => 'go'],
            ),
        ]);
        self::assertFalse($opened['success'], json_encode($opened));
        self::assertSame('GAME_INVALID', $opened['error']['code'] ?? null, json_encode($opened));
        self::assertSame(10, $this->actionPointsOf($attackerId));
        self::assertSame(0, $this->countActions('strike'));
    }

    /**
     * Мир с карточками поддержки.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function supportRules(): array
    {
        $support = static function (array $sides, array $extra = []): array {
            return array_merge([
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
                'support' => array_merge(['sides' => $sides, 'advantage' => 1], $extra),
            ]);
        };

        return array_merge($this->stateActionRules(), [
            'support-any' => [
                'type' => 'ability',
                'name' => 'Support any',
                'spec' => $support(['attacker', 'defender']),
            ],
            'support-attacker' => [
                'type' => 'ability',
                'name' => 'Support attacker',
                'spec' => $support(['attacker']),
            ],
            'support-melee' => [
                'type' => 'ability',
                'name' => 'Support melee',
                'spec' => $support(['attacker', 'defender'], [
                    'advantage' => 2,
                    'profile' => 'strike',
                    'min_mastery' => ['base' => 4, 'size' => 0],
                ]),
            ],
            'support-elite' => [
                'type' => 'ability',
                'name' => 'Support elite',
                'spec' => $support(['attacker', 'defender'], [
                    'advantage' => 2,
                    'profile' => 'strike',
                    'min_mastery' => ['base' => 5, 'size' => 0],
                ]),
            ],
            'plain-action' => [
                'type' => 'ability',
                'name' => 'Plain action',
                'spec' => [
                    'type' => 'action',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'action_components' => [[
                        'type' => 'resource',
                        'resource_code' => 'action-points',
                        'amount' => 1,
                    ]],
                ],
            ],
            'prikrytie' => [
                'type' => 'ability',
                'name' => 'Прикрытие',
                'spec' => [
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
                ],
            ],
        ]);
    }

    /**
     * Поддержка третьей стороны даёт преимущество выбранной стороне.
     *
     * @return void
     */
    public function testSupportReactionAddsAdvantage(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $supporterId = $this->admit($world->getId(), $gameId, 'Supporter', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-support',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $supporterId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-support',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $support = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'support-attacker',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'support-any',
                'actor' => ['type' => 'character', 'id' => $supporterId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($supporterId)->getActualVersion(),
                    'support' => [
                        'side' => 'attacker',
                        'itemInventoryId' => 1,
                        'itemRuleCode' => 'sword',
                        'profileType' => 'strike',
                    ],
                ],
            ],
        ]);
        self::assertTrue($support['success'], json_encode($support));
        self::assertSame(0, $support['data']['spent']);
        self::assertSame(10, $this->actionPointsOf($supporterId));
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $openRow = (new GameActionRepository($gateway))->findOpenWithStrikes($battleId, 'strike');
        self::assertSame('attacker', $openRow['strikes'][0]['supporters'][0]['side'] ?? null);
        $messages = $this->chatMessages($gameId);
        $reactionMessages = array_values(array_filter(
            $messages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'объявляет реакцию'),
        ));
        self::assertCount(1, $reactionMessages);
        $reactionThread = $this->threadOf($reactionMessages[0]);
        self::assertSame('attack-' . $opened['data']['strikeId'], $reactionThread['id'] ?? null);
        $contents = array_map(static fn (MessageRecord $message): string => $message->getContent(), $messages);
        self::assertLessThan(
            $this->messageIndexOf($contents, 'объявляет реакцию'),
            $this->messageIndexOf($contents, 'совершает действие'),
        );

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-support',
            'expectedVersion' => $support['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(8, $this->actionPointsOf($supporterId));
        $this->assertAdvantage('circumstances', 1, $closed['data']['strikes'][0]['roll']['advantages'] ?? []);
    }

    /**
     * Прикрытие объявляется по узлу reaction без списания и переносит цель.
     *
     * @return void
     */
    public function testCoverReactionDeclaresAndResolves(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $covererId = $this->admit($world->getId(), $gameId, 'Coverer', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], [
            ['ruleCode' => 'buckler', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-cover',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $covererId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-cover',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $cover = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'cover-declare',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'prikrytie',
                'actor' => ['type' => 'character', 'id' => $covererId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($covererId)->getActualVersion(),
                    'cover' => [
                        'reaction' => 'block',
                        'blockItemInventoryId' => 1,
                        'blockItemProfileIndex' => 0,
                        'blockItemRuleCode' => 'sword',
                    ],
                ],
            ],
        ]);
        self::assertTrue($cover['success'], json_encode($cover));
        self::assertSame(0, $cover['data']['spent'] ?? null);
        self::assertSame(10, $this->actionPointsOf($covererId));
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $openRow = (new GameActionRepository($gateway))->findOpenWithStrikes($battleId, 'strike');
        self::assertSame('block', $openRow['strikes'][0]['coverers'][0]['reaction'] ?? null);
        $messages = $this->chatMessages($gameId);
        $coverMessages = array_values(array_filter(
            $messages,
            static fn (MessageRecord $message): bool => str_contains($message->getContent(), 'прикрывает'),
        ));
        self::assertCount(1, $coverMessages);
        $coverThread = $this->threadOf($coverMessages[0]);
        self::assertSame('attack-' . $opened['data']['strikeId'], $coverThread['id'] ?? null);

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-cover',
            'expectedVersion' => $cover['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(8, $this->actionPointsOf($covererId));

        if (($closed['data']['closed'] ?? true) === false) {
            $awaiting = $closed['data']['awaiting'][0] ?? null;
            self::assertSame('actual_target', $awaiting['kind'] ?? null);
            $keys = array_map(
                static fn (array $option): string => (string) ($option['key'] ?? ''),
                is_array($awaiting['options'] ?? null) ? $awaiting['options'] : [],
            );
            self::assertContains('character:' . $defenderId, $keys);
            self::assertContains('character:' . $covererId, $keys);

            $resolved = $this->dispatch('game.resolveStrikeWindow', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'w-cover',
                'expectedVersion' => $closed['data']['version'],
                'window' => ['actualTarget' => 'character:' . $covererId],
            ]);
            self::assertTrue($resolved['success'], json_encode($resolved));
            self::assertTrue($resolved['data']['closed'] ?? false, json_encode($resolved));
        }
    }

    /**
     * Защита прикрывающего читает его метку, метка цели не тратится.
     *
     * @return void
     */
    public function testCoverConsumesCovererMarkNotTargetMark(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Cover-Mark-Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Cover-Mark-Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $covererId = $this->admit($world->getId(), $gameId, 'Cover-Mark-Coverer', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], [
            ['ruleCode' => 'buckler', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-cover-mark',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $covererId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $this->seedBattleMarks($battleId, [
            $this->battleMark($defenderId, $attackerId),
            $this->battleMark($covererId, $attackerId),
        ]);
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-cover-mark',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $cover = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'cover-mark-declare',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'prikrytie',
                'actor' => ['type' => 'character', 'id' => $covererId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($covererId)->getActualVersion(),
                    'cover' => [
                        'reaction' => 'block',
                        'blockItemInventoryId' => 1,
                        'blockItemProfileIndex' => 0,
                        'blockItemRuleCode' => 'sword',
                    ],
                ],
            ],
        ]);
        self::assertTrue($cover['success'], json_encode($cover));
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $openRow = (new GameActionRepository($gateway))->findOpenWithStrikes($battleId, 'strike');
        self::assertCount(1, $openRow['strikes'][0]['coverers'] ?? []);
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-cover-mark',
            'expectedVersion' => $cover['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        if (($closed['data']['closed'] ?? true) === false) {
            self::assertCount(2, $this->battleRow($battleId)['marks'] ?? []);
            $resolved = $this->dispatch('game.resolveStrikeWindow', [
                'gameId' => $gameId,
                'battleId' => $battleId,
                'idempotencyKey' => 'w-cover-mark',
                'expectedVersion' => $closed['data']['version'],
                'window' => ['actualTarget' => 'character:' . $covererId],
            ]);
            self::assertTrue($resolved['success'], json_encode($resolved));
            self::assertTrue($resolved['data']['closed'] ?? false, json_encode($resolved));
        } else {
            $advantages = $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? [];
            $penalty = array_values(array_filter(
                $advantages,
                static fn (mixed $row): bool => is_array($row)
                    && ($row['source_code'] ?? null) === 'circumstances'
                    && ($row['delta'] ?? null) === -2,
            ));
            self::assertCount(1, $penalty);
            self::assertSame(
                'character:' . $covererId,
                $closed['data']['strikes'][0]['defenseRoll']['actorKey'] ?? null,
            );
        }

        $marks = $this->battleRow($battleId)['marks'] ?? [];
        self::assertCount(1, $marks);
        self::assertSame($defenderId, $marks[0]['subject']['id'] ?? null);
    }

    /**
     * Провал всех прикрытий ставит удар на выбор фактической цели.
     *
     * @return void
     */
    public function testCoverAllFailedPausesAndResumes(): void
    {
        $rules = $this->supportRules();
        $rules['block'] = [
            'type' => 'ability',
            'name' => 'Block',
            'spec' => [
                'type' => 'action',
                'combat_action' => 'block',
                'action_components' => [[
                    'type' => 'resource',
                    'resource_code' => 'action-points',
                    'amount' => 2,
                ]],
            ],
        ];
        $rules['cursed-shield'] = [
            'type' => 'item',
            'name' => 'Cursed shield',
            'spec' => [
                'type' => 'item',
                'category' => 'shield',
                'proficiency_family_code' => 'shield',
                'block_profile' => [
                    'efficiency' => ['base' => -100, 'size' => 0],
                    'defense' => ['base' => -100, 'size' => 0],
                    'resistances' => [],
                ],
            ],
        ];
        $world = $this->worldWithWeapon(extraRules: $rules);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], [
            ['ruleCode' => 'cursed-shield', 'equipped' => true],
        ]);
        $covererId = $this->admit($world->getId(), $gameId, 'Coverer', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], [
            ['ruleCode' => 'cursed-shield', 'equipped' => true],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-cover-fail',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $covererId],
            ],
        ]);
        self::assertTrue($battle['success'], json_encode($battle));
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-cover-fail',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $cover = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'cover-fail-declare',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'prikrytie',
                'actor' => ['type' => 'character', 'id' => $covererId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($covererId)->getActualVersion(),
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
        self::assertSame(10, $this->actionPointsOf($covererId));

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-cover-fail',
            'expectedVersion' => $cover['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'cursed-shield',
                ],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(false, $closed['data']['closed'] ?? null);
        self::assertSame(8, $this->actionPointsOf($covererId));
        self::assertSame(8, $this->actionPointsOf($defenderId));

        $awaiting = $closed['data']['awaiting'][0] ?? null;
        self::assertSame('actual_target', $awaiting['kind'] ?? null);
        $keys = array_map(
            static fn (array $option): string => (string) ($option['key'] ?? ''),
            is_array($awaiting['options'] ?? null) ? $awaiting['options'] : [],
        );
        self::assertContains('character:' . $defenderId, $keys);
        self::assertContains('character:' . $covererId, $keys);

        $resolved = $this->dispatch('game.resolveStrikeWindow', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'w-cover-fail',
            'expectedVersion' => $closed['data']['version'],
            'window' => ['actualTarget' => 'character:' . $defenderId],
        ]);
        self::assertTrue($resolved['success'], json_encode($resolved));
        self::assertSame(8, $this->actionPointsOf($covererId));
        self::assertSame(8, $this->actionPointsOf($defenderId));

        $messages = $this->chatMessages($gameId);
        $contents = array_map(static fn (MessageRecord $message): string => $message->getContent(), $messages);
        self::assertNotSame([], array_filter(
            $contents,
            static fn (string $content): bool => str_contains($content, 'прикрытие не удаётся'),
        ));
        $blockActors = [];
        foreach ($messages as $message) {
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

        self::assertContains('character:' . $defenderId, $blockActors);
        self::assertContains('character:' . $covererId, $blockActors);
    }

    /**
     * Поддержка защитника усиливает его бросок, а не атакующего.
     *
     * @return void
     */
    public function testSupportReactionForDefender(): void
    {
        $world = $this->worldWithWeapon(1, true, 1, false, null, false, false, [], [], null, $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $supporterId = $this->admit($world->getId(), $gameId, 'Supporter', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-support-def',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $supporterId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-support-def',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $support = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'support-defender',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'support-any',
                'actor' => ['type' => 'character', 'id' => $supporterId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($supporterId)->getActualVersion(),
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

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-support-def',
            'expectedVersion' => $support['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $this->assertAdvantage('circumstances', 1, $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? []);
        $this->assertNoAdvantage('circumstances', 1, $closed['data']['strikes'][0]['roll']['advantages'] ?? []);
    }

    /**
     * Ведущий объявляет поддержку за НПС.
     *
     * @return void
     */
    public function testSupportReactionForNpc(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $supporter = $this->endureNpcSupport($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Supporter',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        $npcId = $supporter['data']['npcId'];
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-support-npc',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'npc', 'id' => $npcId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-support-npc',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $support = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'support-npc',
            'expectedVersion' => $opened['data']['version'],
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => 'support-melee',
                'actor' => ['type' => 'npc', 'id' => $npcId],
                'context' => [
                    'expectedSheetVersion' => $supporter['data']['actualVersion'],
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
        self::assertSame(2, $support['data']['advantage']);

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-support-npc',
            'expectedVersion' => $support['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'dodge'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $this->assertAdvantage('circumstances', 2, $closed['data']['strikes'][0]['defenseRoll']['advantages'] ?? []);
    }

    /**
     * Поддержка отвергает чужую сторону, участников удара и не-карточку.
     *
     * @return void
     */
    public function testSupportReactionRejectsInvalidDeclaration(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $supporterId = $this->admit($world->getId(), $gameId, 'Supporter', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-support-bad',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $supporterId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-support-bad',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $wrongSide = $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'support-attacker',
            'defender',
            $opened['data']['version'],
        );
        $rejected = $this->dispatch('game.runAction', $wrongSide);
        self::assertFalse($rejected['success'], json_encode($rejected));
        self::assertSame('GAME_INVALID', $rejected['error']['code'] ?? null);

        $self = $this->supportEnvelope(
            $gameId,
            $battleId,
            $attackerId,
            'support-any',
            'attacker',
            $opened['data']['version'],
        );
        $rejectedSelf = $this->dispatch('game.runAction', $self);
        self::assertFalse($rejectedSelf['success'], json_encode($rejectedSelf));
        self::assertSame('GAME_INVALID', $rejectedSelf['error']['code'] ?? null);

        $plain = $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'plain-action',
            'attacker',
            $opened['data']['version'],
        );
        $rejectedPlain = $this->dispatch('game.runAction', $plain);
        self::assertFalse($rejectedPlain['success'], json_encode($rejectedPlain));
        self::assertSame('GAME_INVALID', $rejectedPlain['error']['code'] ?? null);

        $first = $this->dispatch('game.runAction', $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'support-any',
            'attacker',
            $opened['data']['version'],
        ));
        self::assertTrue($first['success'], json_encode($first));
        $again = $this->dispatch('game.runAction', $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'support-any',
            'attacker',
            $first['data']['version'],
        ));
        self::assertFalse($again['success'], json_encode($again));
        self::assertSame('GAME_INVALID', $again['error']['code'] ?? null);
        self::assertStringContainsString('already declared', $again['error']['message'] ?? '');
    }

    /**
     * Минимум мастерства выбранного оружия берётся из спеки карточки.
     *
     * @return void
     */
    public function testSupportReactionRequiresMastery(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $supporterId = $this->admit($world->getId(), $gameId, 'Supporter', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-support-mastery',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $supporterId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-support-mastery',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $elite = $this->dispatch('game.runAction', $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'support-elite',
            'attacker',
            $opened['data']['version'],
        ));
        self::assertFalse($elite['success'], json_encode($elite));
        self::assertSame('GAME_INVALID', $elite['error']['code'] ?? null);

        $strong = $this->dispatch('game.runAction', $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'support-melee',
            'attacker',
            $opened['data']['version'],
        ));
        self::assertTrue($strong['success'], json_encode($strong));
        self::assertSame(2, $strong['data']['advantage']);

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-support-mastery',
            'expectedVersion' => $strong['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $this->assertAdvantage('circumstances', 2, $closed['data']['strikes'][0]['roll']['advantages'] ?? []);
    }

    /**
     * Профиль оружия поддержки должен совпадать с профилем удара.
     *
     * @return void
     */
    public function testSupportReactionRejectsWrongWeaponProfile(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->supportRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Attacker', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Defender', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $supporterId = $this->admit($world->getId(), $gameId, 'Supporter', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-support-profile',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
                ['type' => 'character', 'id' => $supporterId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-support-profile',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        $rejected = $this->dispatch('game.runAction', $this->supportEnvelope(
            $gameId,
            $battleId,
            $supporterId,
            'support-melee',
            'attacker',
            $opened['data']['version'],
            'shoot',
        ));
        self::assertFalse($rejected['success'], json_encode($rejected));
        self::assertSame('GAME_INVALID', $rejected['error']['code'] ?? null);
    }

    /**
     * Конверт реакции поддержки.
     *
     * @param int $gameId Игра.
     * @param int $battleId Бой.
     * @param int $supporterId Поддерживающий.
     * @param string $code Карточка.
     * @param string $side Сторона.
     * @param int $expectedVersion Версия боя.
     * @param string $profileType Профиль оружия.
     *
     * @return array<string, mixed> Тело.
     */
    private function supportEnvelope(
        int $gameId,
        int $battleId,
        int $supporterId,
        string $code,
        string $side,
        int $expectedVersion,
        string $profileType = 'strike',
    ): array {
        return [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'support-' . $code . '-' . $side . '-' . $expectedVersion,
            'expectedVersion' => $expectedVersion,
            'action' => [
                'phase' => 'declare',
                'kind' => 'reaction',
                'code' => $code,
                'actor' => ['type' => 'character', 'id' => $supporterId],
                'context' => [
                    'expectedSheetVersion' => $this->characterFacade()->get($supporterId)->getActualVersion(),
                    'support' => [
                        'side' => $side,
                        'itemInventoryId' => 1,
                        'itemRuleCode' => 'sword',
                        'profileType' => $profileType,
                    ],
                ],
            ],
        ];
    }

    /**
     * Мир с процессом серии ударов.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function processStrikeRules(): array
    {
        return array_merge($this->stateActionRules(), [
            'series' => [
                'type' => 'ability',
                'name' => 'Series',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'steps' => [
                            [
                                'code' => 'part-1',
                                'name' => 'Part 1',
                                'costs' => [['amount' => 3, 'resource_code' => 'action-points']],
                                'description' => 'First strike.',
                            ],
                            [
                                'code' => 'part-2',
                                'name' => 'Part 2',
                                'costs' => [['amount' => 2, 'resource_code' => 'action-points']],
                                'description' => 'Second strike.',
                            ],
                        ],
                        'start_step_code' => 'part-1',
                        'failure' => 'end_action',
                        'transition' => [
                            'mode' => 'custom',
                            'edges' => [
                                ['from' => 'part-1', 'to' => 'part-2'],
                                ['from' => 'part-2', 'to' => 'part-2'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Шаг-удар пишет сессию процесса, цену шага и двигает шаг.
     *
     * @return void
     */
    public function testProcessStrikeUsesStepCostAndMovesSession(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processStrikeRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Process Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: [['ruleCode' => 'series', 'level' => 1]]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Process Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-process',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-process-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'series') + ['processRuleCode' => 'series'],
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        self::assertSame(7, $this->actionPointsOf($attackerId));

        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $processes = new GameProcessRepository($gateway);
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame('series', $row->getProcessRuleCode());
        self::assertSame('part-1', $row->getCurrentStepCode());
        self::assertSame('pending', $row->getStepStatus());

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-process-def-1',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame('part-1', $row->getCurrentStepCode());
        self::assertSame('completed', $row->getStepStatus());

        $sheets = $this->dispatch('game.getBattleSheets', ['gameId' => $gameId, 'battleId' => $battleId]);
        self::assertTrue($sheets['success'], json_encode($sheets));
        $view = $sheets['data']['processSessions']['character:' . $attackerId] ?? null;
        self::assertIsArray($view);
        self::assertSame('series', $view['processRuleCode']);
        self::assertSame('part-1', $view['currentStepCode']);
        self::assertSame('completed', $view['stepStatus']);

        $second = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-process-2',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'series')
                + ['processRuleCode' => 'series', 'processStepCode' => 'part-2'],
            ),
        ]);
        self::assertTrue($second['success'], json_encode($second));
        self::assertSame(5, $this->actionPointsOf($attackerId));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame('part-1', $row->getCurrentStepCode());

        $closedSecond = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-process-def-2',
            'expectedVersion' => $second['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closedSecond['success'], json_encode($closedSecond));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame('part-2', $row->getCurrentStepCode());
        self::assertSame('completed', $row->getStepStatus());
    }

    /**
     * Мир с процессом, чей шаг даёт пакет удара.
     *
     * @param array<string, mixed> $extraSuccesses Формула доп. успехов.
     * @param array<int, array<string, mixed>> $counters Счётчики процесса.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function processBonusRules(
        array $extraSuccesses = ['type' => 'fixed', 'value' => 2],
        array $counters = [],
    ): array {
        return array_merge($this->stateActionRules(), [
            'bonus' => [
                'type' => 'ability',
                'name' => 'Bonus',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'counters' => $counters,
                        'steps' => [[
                            'code' => 'hit',
                            'name' => 'Hit',
                            'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                            'description' => 'Hit with bonus.',
                            'strike' => ['extra_successes' => $extraSuccesses],
                        ]],
                        'start_step_code' => 'hit',
                        'exit_step_codes' => ['hit'],
                        'failure' => 'end_action',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Шаг процесса с extra_successes поднимает SR урона.
     *
     * @return void
     */
    public function testProcessStrikeExtraSuccessesRaiseSr(): void
    {
        $closed = $this->resolveProcessBonusStrike();

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(12, $closed['data']['success']);
        self::assertSame(12, $closed['data']['appliedSr']);
        self::assertSame($this->pair(4), $closed['data']['damage']);
        self::assertSame($this->pair(48), $closed['data']['injury']);
        self::assertSame(
            ['successes' => 2, 'rating' => 4],
            $closed['data']['strikes'][0]['roll']['process_bonus'] ?? null,
        );
    }

    /**
     * Один доп. успех процесса стоит два РУ при броске на размер выше сложности.
     *
     * @return void
     */
    public function testProcessStrikeBonusUsesRollSuccessSize(): void
    {
        $closed = $this->resolveProcessBonusStrike(
            ['damage' => ['formula' => ['type' => 'fixed', 'value' => 4]]],
            [],
            ['type' => 'counter', 'counter_code' => 'combo', 'divisor' => 2, 'max_value' => 3],
            [['counter_code' => 'combo', 'initial' => 3]],
        );

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(10, $closed['data']['success']);
        self::assertSame(10, $closed['data']['appliedSr']);
        self::assertSame($this->pair(40), $closed['data']['injury']);
        self::assertSame(
            ['successes' => 1, 'rating' => 2],
            $closed['data']['strikes'][0]['roll']['process_bonus'] ?? null,
        );
    }

    /**
     * Потолок типа урона режет damageSr, но не рейтинг попадания.
     *
     * @return void
     */
    public function testProcessStrikeExtraSuccessesRespectDamageTypeCap(): void
    {
        $closed = $this->resolveProcessBonusStrike(
            ['damage' => ['damage_type_code' => 'capped', 'formula' => ['type' => 'fixed', 'value' => 4]]],
            [
                'capped' => [
                    'type' => 'damage_type',
                    'name' => 'Capped',
                    'spec' => [
                        'forms' => ['genitive' => 'capped', 'dative' => 'capped'],
                        'defense_ignored' => false,
                        'modifies_spell_difficulty' => false,
                        'max_success_rating' => 2,
                    ],
                ],
            ],
        );

        self::assertTrue($closed['success'], json_encode($closed));
        self::assertSame(12, $closed['data']['success']);
        self::assertSame(2, $closed['data']['appliedSr']);
        self::assertSame(2, $closed['data']['srCap']);
        self::assertSame($this->pair(8), $closed['data']['injury']);
    }

    /**
     * target_mode single держит цель первого удара.
     *
     * @return void
     */
    public function testProcessSingleTargetLocksTarget(): void
    {
        $rules = array_merge($this->stateActionRules(), [
            'single-proc' => [
                'type' => 'ability',
                'name' => 'Single',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'target_mode' => 'single',
                        'steps' => [
                            [
                                'code' => 'one',
                                'name' => 'One',
                                'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                                'description' => 'One.',
                            ],
                            [
                                'code' => 'two',
                                'name' => 'Two',
                                'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                                'description' => 'Two.',
                                'strike' => ['advantage' => ['type' => 'fixed', 'value' => 2]],
                            ],
                        ],
                        'start_step_code' => 'one',
                        'failure' => 'end_action',
                    ],
                ],
            ],
        ]);
        $world = $this->worldWithWeapon(extraRules: $rules);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Lock Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: [['ruleCode' => 'single-proc', 'level' => 1]]);
        $firstId = $this->admit($world->getId(), $gameId, 'Lock Guard');
        $secondId = $this->admit($world->getId(), $gameId, 'Other Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-lock',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lock-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $firstId, 'single-proc')
                + ['processRuleCode' => 'single-proc', 'processStepCode' => 'one'],
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lock-def',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($firstId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $sheets = $this->dispatch('game.getBattleSheets', ['gameId' => $gameId, 'battleId' => $battleId]);
        self::assertTrue($sheets['success'], json_encode($sheets));
        $view = $sheets['data']['processSessions']['character:' . $attackerId] ?? null;
        self::assertIsArray($view);
        self::assertSame('two', $view['availableSteps'][0]['code']);
        self::assertSame(
            ['advantage' => 2, 'extraSuccesses' => 0],
            $view['availableSteps'][0]['strike'],
        );

        $switched = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lock-2',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $secondId, 'single-proc')
                + ['processRuleCode' => 'single-proc', 'processStepCode' => 'two'],
            ),
        ]);
        self::assertFalse($switched['success']);

        $sameTarget = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-lock-3',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $firstId, 'single-proc')
                + ['processRuleCode' => 'single-proc', 'processStepCode' => 'two'],
            ),
        ]);
        self::assertTrue($sameTarget['success'], json_encode($sameTarget));
    }

    /**
     * Бьёт шагом процесса с extra_successes и закрывает защиту.
     *
     * @param array<string, mixed> $profileOverrides Профиль урона.
     * @param array<string, mixed> $extraRules Правила типа урона.
     * @param array<string, mixed> $extraSuccesses Формула доп. успехов.
     * @param array<int, array<string, mixed>> $counters Счётчики процесса.
     *
     * @return array<string, mixed> data закрытия.
     */
    private function resolveProcessBonusStrike(
        array $profileOverrides = ['damage' => ['formula' => ['type' => 'fixed', 'value' => 4]]],
        array $extraRules = [],
        array $extraSuccesses = ['type' => 'fixed', 'value' => 2],
        array $counters = [],
    ): array {
        $world = $this->worldWithWeapon(
            extraRules: array_merge($this->processBonusRules($extraSuccesses, $counters), $extraRules),
            profileOverrides: $profileOverrides,
        );
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Bonus Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: [['ruleCode' => 'bonus', 'level' => 1]]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Bonus Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-bonus-' . $gameId,
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-bonus-' . $gameId,
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'bonus')
                + ['processRuleCode' => 'bonus'],
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));

        return $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-bonus-def-' . $gameId,
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
    }

    /**
     * Мир с процессом и карточкой-потомком, закрывающей его.
     *
     * @param int $initial Стартовое Комбо.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function processClosingRules(int $initial = 2): array
    {
        return array_merge($this->stateActionRules(), [
            'closer-proc' => [
                'type' => 'ability',
                'name' => 'Closer Proc',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'counters' => [['counter_code' => 'combo', 'initial' => $initial]],
                        'target_mode' => 'single',
                        'steps' => [[
                            'code' => 'start',
                            'name' => 'Start',
                            'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                            'description' => 'Start.',
                        ]],
                        'start_step_code' => 'start',
                        'failure' => 'end_action',
                    ],
                ],
            ],
            'otkrytie' => [
                'type' => 'ability',
                'name' => 'Opening',
                'spec' => [
                    'type' => 'skill',
                    'parent_ability_code' => 'closer-proc',
                    'closes_parent_process' => [
                        'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                        'strike' => ['advantage' => ['type' => 'fixed', 'value' => 2]],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Обычная атака по прежней цели закрывает процесс потомком.
     *
     * @return void
     */
    public function testProcessClosingAttackResolvesSession(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processClosingRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Close Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: [
            ['ruleCode' => 'closer-proc', 'level' => 1],
            ['ruleCode' => 'otkrytie', 'level' => 1],
        ]);
        $firstId = $this->admit($world->getId(), $gameId, 'Close Guard');
        $secondId = $this->admit($world->getId(), $gameId, 'Other Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-close',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $firstId],
                ['type' => 'character', 'id' => $secondId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $processes = new GameProcessRepository($gateway);

        $started = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-close-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $firstId, 'swing')
                + ['processRuleCode' => 'closer-proc'],
            ),
        ]);
        self::assertTrue($started['success'], json_encode($started));
        $closedStep = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-close-def-1',
            'expectedVersion' => $started['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($firstId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closedStep['success'], json_encode($closedStep));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        $processId = $row->getId();

        $sheets = $this->dispatch('game.getBattleSheets', ['gameId' => $gameId, 'battleId' => $battleId]);
        self::assertTrue($sheets['success'], json_encode($sheets));
        $view = $sheets['data']['processSessions']['character:' . $attackerId] ?? null;
        self::assertIsArray($view);
        self::assertSame('otkrytie', $view['closing']['cardCode'] ?? null);
        self::assertSame(['advantage' => 2, 'extraSuccesses' => 0], $view['closing']['strike'] ?? null);

        $other = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-close-other',
            'expectedVersion' => $closedStep['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $secondId, 'swing')),
        ]);
        self::assertTrue($other['success'], json_encode($other));
        $otherClosed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-close-other-def',
            'expectedVersion' => $other['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($secondId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($otherClosed['success'], json_encode($otherClosed));
        self::assertSame('open', $processes->getById($processId)->getStatus());

        $closing = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-close-2',
            'expectedVersion' => $otherClosed['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $firstId, 'swing')),
        ]);
        self::assertTrue($closing['success'], json_encode($closing));
        $closingClosed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-close-def-2',
            'expectedVersion' => $closing['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($firstId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closingClosed['success'], json_encode($closingClosed));
        self::assertSame('resolved', $processes->getById($processId)->getStatus());
        self::assertNull($processes->findOpenForActor($battleId, 'character', $attackerId));
    }

    /**
     * Закрытие требует выполненного порога счётчика.
     *
     * @return void
     */
    public function testProcessClosingNeedsThreshold(): void
    {
        self::assertSame('open', $this->resolveClosingAttempt(0, true)['status']);
    }

    /**
     * Закрытие требует купленной карточки-потомка.
     *
     * @return void
     */
    public function testProcessClosingNeedsOwnedCard(): void
    {
        self::assertSame('open', $this->resolveClosingAttempt(2, false)['status']);
    }

    /**
     * Открывает процесс и бьёт обычной атакой по прежней цели.
     *
     * @param int $initial Стартовое Комбо.
     * @param bool $owned Куплен ли потомок.
     *
     * @return array{processId: int, status: string} Итог.
     */
    private function resolveClosingAttempt(int $initial, bool $owned): array
    {
        $world = $this->worldWithWeapon(extraRules: $this->processClosingRules($initial));
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $abilities = [['ruleCode' => 'closer-proc', 'level' => 1]];
        if ($owned) {
            $abilities[] = ['ruleCode' => 'otkrytie', 'level' => 1];
        }

        $attackerId = $this->admit($world->getId(), $gameId, 'Attempt Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: $abilities);
        $defenderId = $this->admit($world->getId(), $gameId, 'Attempt Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-attempt-' . $gameId,
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $processes = new GameProcessRepository($gateway);
        $started = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-attempt-' . $gameId,
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'swing')
                + ['processRuleCode' => 'closer-proc'],
            ),
        ]);
        self::assertTrue($started['success'], json_encode($started));
        $closedStep = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-attempt-def-' . $gameId,
            'expectedVersion' => $started['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closedStep['success'], json_encode($closedStep));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        $processId = $row->getId();

        $attack = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-attempt-2-' . $gameId,
            'expectedVersion' => $closedStep['data']['version'],
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId, 'swing')),
        ]);
        self::assertTrue($attack['success'], json_encode($attack));
        $closedAttack = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-attempt-2-def-' . $gameId,
            'expectedVersion' => $attack['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closedAttack['success'], json_encode($closedAttack));

        return ['processId' => $processId, 'status' => $processes->getById($processId)->getStatus()];
    }

    /**
     * Мир с процессом, который ведёт счётчик Комбо.
     *
     * @return array<string, array<string, mixed>> Правила.
     */
    private function processCounterRules(): array
    {
        return array_merge($this->stateActionRules(), [
            'combo-proc' => [
                'type' => 'ability',
                'name' => 'Combo',
                'spec' => [
                    'type' => 'process',
                    'zones' => ['or' => ['kind' => 'automatic']],
                    'process' => [
                        'counters' => [['counter_code' => 'combo', 'initial' => 0]],
                        'steps' => [
                            [
                                'code' => 'start',
                                'name' => 'Start',
                                'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                                'description' => 'Start.',
                                'counter_on_success' => [['counter_code' => 'combo', 'delta' => 1]],
                            ],
                            [
                                'code' => 'build',
                                'name' => 'Build',
                                'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                                'description' => 'Build.',
                                'counter_on_success' => [['counter_code' => 'combo', 'delta' => 1]],
                            ],
                            [
                                'code' => 'finish',
                                'name' => 'Finish',
                                'costs' => [['amount' => 1, 'resource_code' => 'action-points']],
                                'description' => 'Finish.',
                                'requires_counters' => [['counter_code' => 'combo', 'min' => 2]],
                                'counter_on_success' => [['counter_code' => 'combo', 'set' => 0]],
                            ],
                        ],
                        'start_step_code' => 'start',
                        'failure' => 'end_action',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Счётчик растёт на успехе шага, держит порог и сбрасывается set.
     *
     * @return void
     */
    public function testProcessCountersRiseHoldThresholdAndReset(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processCounterRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Combo Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: [['ruleCode' => 'combo-proc', 'level' => 1]]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Combo Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-combo',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $processes = new GameProcessRepository($gateway);

        $started = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'combo-proc')
                + ['processRuleCode' => 'combo-proc'],
            ),
        ]);
        self::assertTrue($started['success'], json_encode($started));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame(['combo' => 0], $row->getCounters());

        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-def-1',
            'expectedVersion' => $started['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame(['combo' => 1], $row->getCounters());

        $blocked = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-finish',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'combo-proc')
                + ['processRuleCode' => 'combo-proc', 'processStepCode' => 'finish'],
            ),
        ]);
        self::assertFalse($blocked['success']);

        $built = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-2',
            'expectedVersion' => $closed['data']['version'],
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'combo-proc')
                + ['processRuleCode' => 'combo-proc', 'processStepCode' => 'build'],
            ),
        ]);
        self::assertTrue($built['success'], json_encode($built));
        $closedSecond = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-def-2',
            'expectedVersion' => $built['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closedSecond['success'], json_encode($closedSecond));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame(['combo' => 2], $row->getCounters());

        $finish = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-3',
            'expectedVersion' => $closedSecond['data']['version'],
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'combo-proc')
                + ['processRuleCode' => 'combo-proc', 'processStepCode' => 'finish'],
            ),
        ]);
        self::assertTrue($finish['success'], json_encode($finish));
        $closedFinish = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-combo-def-3',
            'expectedVersion' => $finish['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closedFinish['success'], json_encode($closedFinish));
        $row = $processes->findOpenForActor($battleId, 'character', $attackerId);
        self::assertNotNull($row);
        self::assertSame(['combo' => 0], $row->getCounters());
        self::assertSame('completed', $row->getStepStatus());
    }

    /**
     * Обычное прерывание гасит сессию процесса.
     *
     * @return void
     */
    public function testAbortProcessClosesSession(): void
    {
        $world = $this->worldWithWeapon(extraRules: $this->processStrikeRules());
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $attackerId = $this->admit($world->getId(), $gameId, 'Abort Hero', [], [
            'resources' => [['ruleCode' => 'action-points', 'current' => 10]],
        ], abilities: [['ruleCode' => 'series', 'level' => 1]]);
        $defenderId = $this->admit($world->getId(), $gameId, 'Abort Guard');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-process-abort',
            'participants' => [
                ['type' => 'character', 'id' => $attackerId],
                ['type' => 'character', 'id' => $defenderId],
            ],
        ]);
        $battleId = $battle['data']['battleId'];
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-process-abort-1',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope(
                $this->characterAttack($attackerId, $defenderId, 'series') + ['processRuleCode' => 'series'],
            ),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-process-abort-def',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(
                ['reaction' => 'ignore'],
                $this->characterFacade()->get($defenderId)->getActualVersion(),
            ),
        ]);
        self::assertTrue($closed['success'], json_encode($closed));

        $aborted = $this->dispatch('game.abortProcess', [
            'gameId' => $gameId,
            'idempotencyKey' => 's-process-abort',
            'battleId' => $battleId,
            'expectedBattleVersion' => $closed['data']['version'],
            'expectedSheetVersion' => $this->characterFacade()->get($attackerId)->getActualVersion(),
            'mode' => 'normal',
        ]);
        self::assertTrue($aborted['success'], json_encode($aborted));
        self::assertTrue($aborted['data']['aborted']);
        self::assertSame('series', $aborted['data']['processRuleCode']);
        self::assertSame('part-1', $aborted['data']['stepCode']);

        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $processes = new GameProcessRepository($gateway);
        self::assertNull($processes->findOpenForActor($battleId, 'character', $attackerId));
    }

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
        ?string $dodgeSoak = null,
        array $extraRules = [],
        bool $withActionDamage = false,
        array $profileOverrides = [],
        ?array $occupyHands = null,
        bool $hitConcentration = false,
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
            . ($extraCards === [] ? '' : '-ex' . substr(md5((string) json_encode($extraCards)), 0, 6))
            . ($dodgeSoak === null ? '' : '-ds' . substr(md5($dodgeSoak), 0, 6))
            . ($extraRules === [] ? '' : '-er' . substr(md5((string) json_encode($extraRules)), 0, 6))
            . ($withActionDamage ? '-ad' : '')
            . ($occupyHands === null ? '' : '-h' . substr(md5((string) json_encode($occupyHands)), 0, 6)),
        );
        $check = [
            'allow_characteristic_override' => false,
            'allowed_modes' => 'both',
            'ordinary_root' => true,
            'concentration_token' => false,
            'willpower' => false,
            'unstable_check' => false,
            'hit_check' => true,
            'concentration_token' => $hitConcentration,
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
                'zones' => ['or' => ['kind' => 'automatic']],
                'action_components' => $swingComponents,
                ...($swingEffects === [] ? [] : ['action_effects' => $swingEffects]),
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('dodge', new RuleVersionBody('ability', 'Dodge', '', [
                'type' => 'action',
                'combat_action' => 'dodge',
                'dodge_efficiency' => ['base' => 4, 'size' => -1],
                ...(($dodgeSoak ?? ($soakCards >= 1 ? 'agility' : null)) === null
                    ? []
                    : ['dodge_soak' => $dodgeSoak ?? 'agility']),
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
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
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
                        ] : ($withActionDamage ? [
                            'damage' => [
                                'damage_type_code' => 'cut',
                                'formula' => [
                                    'type' => 'actionCharacteristic',
                                    'action' => 'swing',
                                    'characteristic' => 'strength',
                                    'modifier' => [],
                                ],
                            ],
                        ] : [])),
                        ...$profileOverrides,
                    ]],
                ],
                'block_profile' => [
                    'efficiency' => ['base' => 3, 'size' => 0],
                    'defense' => ['base' => 1, 'size' => 0],
                    'resistances' => [],
                ],
                ...($occupyHands === null ? [] : ['occupy_hands' => $occupyHands]),
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
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
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
            RuleCommitEntry::put('reaction', new RuleVersionBody('characteristic', 'Reaction', '', [
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
            $entries[] = RuleCommitEntry::put('agility', new RuleVersionBody('characteristic', 'Agility', '', [], [], [], 'needs_work'));
            $entries[] = RuleCommitEntry::put('rapier', new RuleVersionBody('item', 'Rapier', '', [
                'category' => 'weapon',
                'proficiency_family_code' => 'melee',
                'weapon' => [
                    'weapon_profiles' => [[
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
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
                        'type' => 'strike', 'distance' => ['min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]], 'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]]],
                        'accuracy' => ['base' => 3, 'size' => 0],
                        'dodge_benefit' => 0,
                    ]],
                ],
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
            $zones = ['or' => ['kind' => 'automatic']];
            $requirements = [];
            $type = 'action';
            $parent = null;
            $spec = [];
            if (is_array($definition) && array_key_exists('effects', $definition)) {
                $effects = is_array($definition['effects']) ? $definition['effects'] : [];
                $components = is_array($definition['components'] ?? null) ? $definition['components'] : [];
                $combatAction = is_string($definition['combat_action'] ?? null) ? $definition['combat_action'] : null;
                $zones = is_array($definition['zones'] ?? null) ? $definition['zones'] : $zones;
                $requirements = is_array($definition['requirements'] ?? null) ? $definition['requirements'] : [];
                $type = is_string($definition['type'] ?? null) ? $definition['type'] : 'action';
                $parent = is_string($definition['parent'] ?? null) ? $definition['parent'] : null;
                $spec = is_array($definition['spec'] ?? null) ? $definition['spec'] : [];
            }

            $entries[] = RuleCommitEntry::put($code, new RuleVersionBody('ability', ucfirst($code), '', [
                'type' => $type,
                'zones' => $zones,
                ...($combatAction === null ? [] : ['combat_action' => $combatAction]),
                ...($type === 'action' ? ['action_components' => $components] : []),
                ...($requirements === [] ? [] : ['requirements' => $requirements]),
                ...($parent === null ? [] : ['parent_ability_code' => $parent]),
                ...($effects === [] ? [] : ['action_effects' => $effects]),
                ...$spec,
            ], [], [], 'needs_work'));
        }

        foreach ($extraRules as $code => $definition) {
            $mechanics = is_array($definition['mechanics'] ?? null) ? $definition['mechanics'] : [];
            if (($definition['roll'] ?? false) === true) {
                $mechanics = [[
                    'mechanic_id' => $rollId,
                    'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
                ]];
            }

            $entries[] = RuleCommitEntry::put($code, new RuleVersionBody(
                is_string($definition['type'] ?? null) ? $definition['type'] : 'simple',
                is_string($definition['name'] ?? null) ? $definition['name'] : ucfirst($code),
                '',
                is_array($definition['spec'] ?? null) ? $definition['spec'] : [],
                is_array($definition['keywordIds'] ?? null) ? $definition['keywordIds'] : [],
                $mechanics,
                'needs_work',
            ));
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
     * Проверяет преимущество по источнику и дельте без учёта подписи.
     *
     * @param string $source Источник.
     * @param int $delta Дельта.
     * @param array<int, mixed> $advantages Записи броска.
     *
     * @return void
     */
    private function assertAdvantage(string $source, int $delta, array $advantages): void
    {
        $matched = array_values(array_filter(
            $advantages,
            static fn (mixed $row): bool => is_array($row)
                && ($row['source_code'] ?? null) === $source
                && ($row['delta'] ?? null) === $delta,
        ));
        self::assertNotEmpty($matched, 'Advantage ' . $source . ' ' . $delta . ' is missing');
    }

    /**
     * Проверяет отсутствие преимущества по источнику и дельте.
     *
     * @param string $source Источник.
     * @param int $delta Дельта.
     * @param array<int, mixed> $advantages Записи броска.
     *
     * @return void
     */
    private function assertNoAdvantage(string $source, int $delta, array $advantages): void
    {
        $matched = array_values(array_filter(
            $advantages,
            static fn (mixed $row): bool => is_array($row)
                && ($row['source_code'] ?? null) === $source
                && ($row['delta'] ?? null) === $delta,
        ));
        self::assertSame([], $matched, 'Advantage ' . $source . ' ' . $delta . ' is present');
    }

    /**
     * Выбор атаки мечом.
     *
     * @param int $attackerId Атакующий.
     * @param int $defenderId Защитник.
     * @param string $actionRuleCode Карточка действия.
     * @param string $itemRuleCode Предмет.
     *
     * @return array<string, mixed> Тело.
     */
    private function characterAttack(
        int $attackerId,
        int $defenderId,
        string $actionRuleCode = 'swing',
        string $itemRuleCode = 'sword',
    ): array {
        return [
            'attacker' => ['type' => 'character', 'id' => $attackerId],
            'defender' => ['type' => 'character', 'id' => $defenderId],
            'actionRuleCode' => $actionRuleCode,
            'itemInventoryId' => 1,
            'itemRuleCode' => $itemRuleCode,
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
     * Объявляет удар по NPC заданной карточкой.
     *
     * @param string $actionRuleCode Карточка.
     * @param array<int, array<string, mixed>> $abilities Способности атакующего.
     * @param string $itemRuleCode Оружие.
     * @param array<int, array<string, mixed>> $inventory Инвентарь.
     * @param array<string, array<string, mixed>> $extraCards Карточки мира.
     * @param array<string, array<string, mixed>> $extraRules Правила мира.
     *
     * @return array<string, mixed> Ответ объявления.
     */
    private function declareActionCard(
        string $actionRuleCode,
        array $abilities = [],
        string $itemRuleCode = 'sword',
        array $inventory = [],
        array $extraCards = [],
        array $extraRules = [],
    ): array {
        $world = $this->worldWithWeapon(extraCards: $extraCards, extraRules: $extraRules);
        $gameId = $this->addGame($world->getId(), $this->worldRevision());
        $characterId = $this->admit(
            $world->getId(),
            $gameId,
            'Hero-' . $actionRuleCode . '-' . $gameId,
            [],
            [],
            $inventory,
            abilities: $abilities,
        );
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $npc = $this->endureNpc($gameId, $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]));
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b-card-' . $gameId,
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);

        return $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-card-' . $gameId,
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $characterId],
                'defender' => ['type' => 'npc', 'id' => $npc['data']['npcId']],
                'actionRuleCode' => $actionRuleCode,
                'itemInventoryId' => 1,
                'itemRuleCode' => $itemRuleCode,
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ]);
    }

    /**
     * Закрытие удара с формулой урона от характеристики действия.
     *
     * @param array<int, array<string, mixed>> $cardEffects Эффекты карточки.
     * @param string $reaction Реакция защиты.
     *
     * @return array<string, mixed> data закрытия.
     */
    private function resolveActionDamage(array $cardEffects, string $reaction = 'ignore'): array
    {
        $world = $this->worldWithWeapon(withActionDamage: true, cardEffects: $cardEffects);

        return $this->resolveDefense($world->getId(), [], 'sword', true, $reaction);
    }

    /**
     * Эффект бонуса к характеристике урона от РУ.
     *
     * @param int $floorDiv Делитель.
     * @param int $cap Потолок.
     *
     * @return array<string, mixed> Эффект.
     */
    private function successRatingEffect(int $floorDiv = 2, int $cap = 3): array
    {
        return [
            'type' => 'current_action_attack_characteristic_from_success_rating',
            'floor_div' => $floorDiv,
            'cap' => $cap,
            'scope' => ['components' => ['strike'], 'hit_count' => 1],
        ];
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
        ?array $choicePurchases = null,
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
            $this->ownerUserId,
            $choicePurchases,
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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's-resist',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope([
                'attacker' => ['type' => 'character', 'id' => $attackerId],
                'defender' => ['type' => 'character', 'id' => $defenderId],
                'actionRuleCode' => 'swing',
                'itemInventoryId' => 1,
                'itemRuleCode' => $itemRuleCode,
                'profileType' => 'strike',
                'profileIndex' => 0,
            ]),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-resist',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope($reaction === 'block'
                ? [
                    'reaction' => 'block',
                    'blockItemInventoryId' => 1,
                    'blockItemProfileIndex' => 0,
                    'blockItemRuleCode' => 'buckler',
                ]
                : ['reaction' => $reaction], $defenderVersion),
        ]);
        if (!$expectClose) {
            return $closed;
        }

        self::assertTrue($closed['success'], json_encode($closed));
        if ($replay) {
            $again = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd-resist',
            'expectedVersion' => 2,
            'action' => $this->strikeDefenseEnvelope(['reaction' => $reaction], $defenderVersion),
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
        ], [
            'characteristic_code' => 'will',
            'mode' => 'purchased',
            'base' => ['base' => 3, 'size' => 0],
            'purchase' => [['cost' => 2, 'value' => ['base' => 3, 'size' => 0]]],
        ]];
        if ($soakCards >= 1) {
            $rows[] = [
                'characteristic_code' => 'agility',
                'mode' => 'purchased',
                'base' => ['base' => 4, 'size' => 0],
                'purchase' => [
                    ['cost' => 2, 'value' => ['base' => 4, 'size' => 0]],
                    ['cost' => 4, 'value' => ['base' => 5, 'size' => 0]],
                ],
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
        ?array $choicePurchases = null,
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
            ] : $inventory, $choicePurchases, $abilities),
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
    private function storedChoices(
        string $name,
        array $inventory = [],
        ?array $choicePurchases = null,
        array $abilities = [],
    ): array {
        return [
            'name' => $name,
            'raceCode' => 'human',
            'abilities' => $abilities,
            'inventory' => $this->inventoryWithIds($inventory),
            'characteristicPurchases' => $choicePurchases
                ?? [['characteristicCode' => 'strength', 'cost' => 2]],
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
    private function endureNpcSupport(int $gameId, array $npc): array
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $npcId = $npc['data']['npcId'] ?? null;
        self::assertIsInt($npcId);
        $repo = new GameNpcRepository($gateway);
        $record = $repo->getById($npcId);
        self::assertSame($gameId, $record->getGameId());
        $version = $record->getVersion();
        $sheet = is_array($version['sheet'] ?? null) ? $version['sheet'] : [];
        $sheet['characteristicPurchases'] = [];
        $version['sheet'] = $sheet;
        $choices = is_array($version['choices'] ?? null) ? $version['choices'] : [];
        $choices['raceCode'] = 'human';
        $choices['inventory'] = [['id' => 1, 'ruleCode' => 'sword', 'equipped' => true]];
        $choices['abilities'] = [['ruleCode' => 'support-melee', 'level' => 1]];
        $choices['characteristicPurchases'] = [['characteristicCode' => 'strength', 'cost' => 2]];
        $version['choices'] = $choices;
        $saved = $repo->replaceVersion($npcId, $version, $record->getActualVersion());
        $npc['data']['actualVersion'] = $saved->getActualVersion();

        return $npc;
    }

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
        $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-roll-adjust',
            'expectedVersion' => 1,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
        ]);
        self::assertTrue($opened['success'], json_encode($opened));
        $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-roll-adjust',
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
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
            $opened = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 's-tail-' . $index,
            'expectedVersion' => $version,
            'action' => $this->strikeEnvelope($this->characterAttack($attackerId, $defenderId)),
            ]);
            self::assertTrue($opened['success'], json_encode($opened));
            $closed = $this->dispatch('game.runAction', [
            'gameId' => $gameId,
            'battleId' => $battleId,
            'idempotencyKey' => 'd-tail-' . $index,
            'expectedVersion' => $opened['data']['version'],
            'action' => $this->strikeDefenseEnvelope(['reaction' => 'ignore'], $this->characterFacade()->get($defenderId)->getActualVersion()),
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
        return $this->messagesByThreadKinds($gameId, ['attack', 'spell']);
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
        return $this->messagesByThreadKinds($gameId, [$kind]);
    }

    /**
     * Сообщения игрового чата одного из видов треда.
     *
     * @param int $gameId Игра.
     * @param list<string> $kinds Виды тредов.
     *
     * @return array<int, MessageRecord> Сообщения.
     */
    private function messagesByThreadKinds(int $gameId, array $kinds): array
    {
        $chatId = $this->gameFacade()->get($gameId)->getGameChatId();
        self::assertIsInt($chatId);
        $messages = array_reverse($this->chats()->findMessagePage($chatId, $this->ownerUserId, 100, 0)->getItems());

        return array_values(array_filter(
            $messages,
            fn (MessageRecord $message): bool => in_array($this->threadKind($message), $kinds, true),
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
