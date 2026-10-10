<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameEconomyMysqlTest extends TestCase
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
     * Покупка, конфликт версии, повтор ключа и выдача в NPC.
     *
     * @return void
     */
    public function testBuyConflictReplayAndNpcLoot(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [], [], [], 'needs_work')),
        ]);
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Market',
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
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $world->getId(),
            'rulesRevision' => 2,
            'name' => 'Hero',
            'choices' => [
                'name' => 'Hero',
                'raceCode' => 'human',
                'abilities' => [],
                'inventory' => [],
                'characteristicPurchases' => [],
                'customRules' => [],
                'money' => 100,
                'active' => true,
            ],
            'sheet' => ['money' => 100],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 1, 1);
        $shop = $this->dispatch('game.replaceShop', [
            'gameId' => $gameId,
            'positions' => [[
                'ruleCode' => 'sword',
                'buyPrice' => 10,
                'sellPrice' => 4,
                'quantity' => 3,
            ]],
            'expectedPositions' => [],
        ]);
        self::assertTrue($shop['success'], json_encode($shop));
        $bought = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-1',
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 2,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 1]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => 1]],
            ],
        ]);
        self::assertTrue($bought['success'], json_encode($bought));
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);
        self::assertSame(1, $this->dispatch('game.getShop', ['gameId' => $gameId])['data']['positions'][0]['quantity']);
        $stale = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-2',
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 1,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 1]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => 2]],
            ],
        ]);
        self::assertFalse($stale['success']);
        self::assertSame('GAME_CONFLICT', $stale['error']['code']);
        self::assertArrayHasKey('currentVersion', $stale['error']['details']);
        self::assertArrayHasKey('choices', $stale['error']['details']);
        self::assertArrayHasKey('sheet', $stale['error']['details']);
        self::assertArrayNotHasKey('currentSheet', $stale['error']['details']);
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);
        self::assertSame(1, $this->dispatch('game.getShop', ['gameId' => $gameId])['data']['positions'][0]['quantity']);
        $replay = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-1',
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 2,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 1]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => 1]],
            ],
        ]);
        self::assertTrue($replay['success'], json_encode($replay));
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);
        $npc = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'all', 'userIds' => [], 'sections' => []],
        ]);
        self::assertTrue($npc['success'], json_encode($npc));
        $loot = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'loot-1',
            'parts' => [[
                'kind' => 'loot',
                'ruleCode' => 'sword',
                'quantity' => 1,
                'to' => ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ]],
            'expectedVersions' => [
                'characters' => [],
                'npcs' => [['npcId' => $npc['data']['npcId'], 'actualVersion' => 1]],
                'positions' => [],
            ],
        ]);
        self::assertTrue($loot['success'], json_encode($loot));
        $again = $this->dispatch('game.getNpc', ['gameId' => $gameId, 'npcId' => $npc['data']['npcId']]);
        self::assertSame(1, $again['data']['version']['choices']['inventory'][0]['quantity']);
        self::assertSame('', $again['data']['version']['choices']['raceCode']);
        $npcStale = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'loot-stale',
            'parts' => [[
                'kind' => 'loot',
                'ruleCode' => 'sword',
                'quantity' => 1,
                'to' => ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ]],
            'expectedVersions' => [
                'characters' => [],
                'npcs' => [['npcId' => $npc['data']['npcId'], 'actualVersion' => 1]],
                'positions' => [],
            ],
        ]);
        self::assertFalse($npcStale['success']);
        self::assertSame('GAME_CONFLICT', $npcStale['error']['code']);
        self::assertSame(2, $npcStale['error']['details']['currentVersion']);
        self::assertArrayHasKey('choices', $npcStale['error']['details']);
        self::assertArrayHasKey('sheet', $npcStale['error']['details']);
        self::assertArrayNotHasKey('currentSheet', $npcStale['error']['details']);
        self::assertSame(1, $this->dispatch('game.getNpc', [
            'gameId' => $gameId,
            'npcId' => $npc['data']['npcId'],
        ])['data']['version']['choices']['inventory'][0]['quantity']);
        $stray = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-stray',
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 1,
                'note' => 'extra',
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 2]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => 2]],
            ],
        ]);
        self::assertFalse($stray['success']);
        self::assertSame('GAME_INVALID', $stray['error']['code']);
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);
        $outsiderId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $world->getId(),
            'rulesRevision' => 2,
            'name' => 'Outsider',
            'choices' => [
                'name' => 'Outsider',
                'raceCode' => 'human',
                'abilities' => [],
                'inventory' => [],
                'characteristicPurchases' => [],
                'customRules' => [],
                'money' => 0,
                'active' => true,
            ],
            'sheet' => ['money' => 0],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $miss = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'loot-outsider',
            'parts' => [[
                'kind' => 'loot',
                'ruleCode' => 'sword',
                'quantity' => 1,
                'to' => ['type' => 'character', 'id' => $outsiderId],
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $outsiderId, 'actualVersion' => 1]],
                'npcs' => [],
                'positions' => [],
            ],
        ]);
        self::assertFalse($miss['success']);
        self::assertSame('GAME_NOT_FOUND', $miss['error']['code']);
        self::assertSame([], $this->characterFacade()->get($outsiderId)->getChoices()['inventory']);
        $secondId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $world->getId(),
            'rulesRevision' => 2,
            'name' => 'Ally',
            'choices' => [
                'name' => 'Ally',
                'raceCode' => 'human',
                'abilities' => [],
                'inventory' => [['ruleCode' => 'sword', 'quantity' => 1, 'equipped' => false]],
                'characteristicPurchases' => [],
                'customRules' => [],
                'money' => 0,
                'active' => true,
            ],
            'sheet' => ['money' => 0],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $this->membershipFacade()->submit($gameId, $secondId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $secondId, 1, 1);
        $swap = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'swap-1',
            'parts' => [
                [
                    'kind' => 'transfer_item',
                    'from' => ['type' => 'character', 'id' => $characterId],
                    'to' => ['type' => 'character', 'id' => $secondId],
                    'ruleCode' => 'sword',
                    'quantity' => 1,
                ],
                [
                    'kind' => 'transfer_item',
                    'from' => ['type' => 'character', 'id' => $secondId],
                    'to' => ['type' => 'character', 'id' => $characterId],
                    'ruleCode' => 'sword',
                    'quantity' => 1,
                ],
            ],
            'expectedVersions' => [
                'characters' => [
                    ['characterId' => $characterId, 'actualVersion' => 2],
                    ['characterId' => $secondId, 'actualVersion' => 1],
                ],
                'npcs' => [],
                'positions' => [],
            ],
        ]);
        self::assertTrue($swap['success'], json_encode($swap));
        self::assertSame(2, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame(1, $this->characterFacade()->get($secondId)->getActualVersion());
        $strangerId = $this->gameUserAccounts()->addFromInput([
            'login' => 'stranger',
            'name' => 'Stranger',
        ]);
        $this->setActor($strangerId, [GamePermissionKeys::CREATE]);
        $hidden = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-1',
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 2,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 1]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => 1]],
            ],
        ]);
        self::assertFalse($hidden['success']);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $this->gameFacade()->update($gameId, GamePatch::fromNormalized([
            'name' => 'Market',
            'status' => 'completed',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
            'rulesRevision' => 2,
        ]));
        $closedReplay = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-1',
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 2,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 1]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => 1]],
            ],
        ]);
        self::assertTrue($closedReplay['success'], json_encode($closedReplay));
        $closedNew = $this->dispatch('game.applyEconomy', [
            'gameId' => $gameId,
            'idempotencyKey' => 'buy-closed',
            'parts' => [[
                'kind' => 'discard',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => 1,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => 2]],
                'npcs' => [],
                'positions' => [],
            ],
        ]);
        self::assertFalse($closedNew['success']);
        self::assertSame('GAME_INVALID', $closedNew['error']['code']);
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $permissionKeys, false));
    }

    /**
     * Action.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }
}
