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
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameManualSheetMysqlTest extends TestCase
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
     * Задаёт current, пишет чат и delivery, replay без второго эффекта.
     *
     * @return void
     */
    public function testSetResourceWritesSheetChatAndReplay(): void
    {
        $scene = $this->scene();
        $body = $this->resourceBody($scene, 'k1', 1);
        $first = $this->dispatch('game.manualSheet', $body);
        self::assertTrue($first['success'], json_encode($first));
        self::assertIsInt($first['data']['actualVersion'] ?? null);
        $sheet = $this->characterFacade()->get($scene['hero'])->getSheet();
        self::assertSame(1, $sheet['resources'][0]['current']);
        $replay = $this->dispatch('game.manualSheet', $body);
        self::assertTrue($replay['success']);
        self::assertSame(1, $this->characterFacade()->get($scene['hero'])->getSheet()['resources'][0]['current']);
        $rows = $this->deliveryRows();
        self::assertSame('manual_sheet', $rows[0]['source'] ?? null);
        self::assertSame(1, $this->commandCount());
    }

    /**
     * add/remove состояния по id. Без сессии отказ.
     *
     * @return void
     */
    public function testAddAndRemoveStateById(): void
    {
        $scene = $this->scene();
        $added = $this->dispatch('game.manualSheet', [
            'gameId' => $scene['gameId'],
            'idempotencyKey' => 'add-1',
            'operation' => 'addState',
            'target' => ['kind' => 'character', 'id' => $scene['hero']],
            'expectedSheetVersion' => $scene['sheetVersion'],
            'details' => ['stateRuleCode' => 'mark'],
        ]);
        self::assertTrue($added['success'], json_encode($added));
        $states = $this->characterFacade()->get($scene['hero'])->getSheet()['states'];
        self::assertIsArray($states[0] ?? null);
        self::assertIsInt($states[0]['id'] ?? null);
        $removed = $this->dispatch('game.manualSheet', [
            'gameId' => $scene['gameId'],
            'idempotencyKey' => 'rm-1',
            'operation' => 'removeState',
            'target' => ['kind' => 'character', 'id' => $scene['hero']],
            'expectedSheetVersion' => $added['data']['actualVersion'],
            'details' => ['id' => $states[0]['id']],
        ]);
        self::assertTrue($removed['success'], json_encode($removed));
        self::assertSame([], $this->characterFacade()->get($scene['hero'])->getSheet()['states'] ?? []);
    }

    /**
     * Игрок не правит NPC.
     *
     * @return void
     */
    public function testPlayerDeniedOnForeignCharacter(): void
    {
        $scene = $this->scene();
        $player = $this->gameUserAccounts()->addFromInput([
            'login' => 'p-' . substr(uniqid('', true), -8),
            'name' => 'P',
        ]);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $scene['gameId'],
            'userId' => $player,
            'role' => 'player',
        ]));
        $this->setActor($player, [GamePermissionKeys::VIEW_ALL]);
        $denied = $this->dispatch('game.manualSheet', $this->resourceBody($scene, 'deny-1', 1));
        self::assertFalse($denied['success']);
        self::assertSame('AUTH_DENIED', $denied['error']['code']);
    }

    /**
     * @return array{gameId: int, hero: int, sheetVersion: int}
     */
    private function scene(): array
    {
        $world = $this->addWorldWithRevision('ms-' . substr(uniqid('', true), -8));
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('strength', new RuleVersionBody('characteristic', 'Strength', '', [], [], [], 'needs_work')),
            RuleCommitEntry::put('ap', new RuleVersionBody('resource', 'AP', '', [
                'is_dimensional' => false,
                'auto_add' => true,
                'check_token' => false,
                'turn_refill' => true,
                'limit' => ['base' => 4, 'adjustments' => []],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('mark', new RuleVersionBody('state', 'Mark', '', [
                'value_type' => 'flag',
                'aggregation' => 'independent',
            ], [], [], 'needs_work')),
        ]);
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Manual',
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
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        $hero = $this->admitHero($world->getId(), $gameId);
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);

        return [
            'gameId' => $gameId,
            'hero' => $hero,
            'sheetVersion' => $this->characterFacade()->get($hero)->getActualVersion(),
        ];
    }

    /**
     * @param array{gameId: int, hero: int, sheetVersion: int} $scene Сцена.
     * @param string $key Ключ.
     * @param int $current Current.
     *
     * @return array<string, mixed> Тело.
     */
    private function resourceBody(array $scene, string $key, int $current): array
    {
        return [
            'gameId' => $scene['gameId'],
            'idempotencyKey' => $key,
            'operation' => 'setResource',
            'target' => ['kind' => 'character', 'id' => $scene['hero']],
            'expectedSheetVersion' => $scene['sheetVersion'],
            'details' => ['resourceCode' => 'ap', 'current' => $current],
        ];
    }

    private function admitHero(int $spaceId, int $gameId): int
    {
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 2,
            'name' => 'Hero',
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $this->characterFacade()->replaceMigrated(
            $characterId,
            'Hero',
            true,
            [
                'name' => 'Hero',
                'raceCode' => 'human',
                'abilities' => [],
                'inventory' => [],
                'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
                'customRules' => [],
                'money' => 0,
                'active' => true,
            ],
            [
                'abilityLevels' => [],
                'resources' => [['ruleCode' => 'ap', 'current' => 4]],
                'characteristicPurchases' => [[
                    'characteristicCode' => 'strength',
                    'value' => ['base' => 4, 'size' => 0],
                ]],
            ],
            2,
            1,
        );
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);

        return $characterId;
    }

    /**
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->gameApplication()->dispatch($action, $payload)->toArray();
    }

    /**
     * @param list<string> $keys Ключи.
     */
    private function setActor(int $userId, array $keys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $keys, false));
    }

    /**
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

    private function commandCount(): int
    {
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return $this->countCommands('manual_sheet');
    }
}
