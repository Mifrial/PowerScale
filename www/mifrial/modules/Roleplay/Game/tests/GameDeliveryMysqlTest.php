<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventListener;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameDeliveryPortFactory;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Game\Service\GameDeliverySync;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Game\Table\GameDeliveryTable;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameDeliveryMysqlTest extends TestCase
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
     * Повтор и сбой доставки лист не пишут второй раз. Догон кладёт одну строку.
     *
     * @return void
     */
    public function testReplayAndFailedDeliveryDoNotRewriteSheet(): void
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
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
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
        self::assertTrue($this->dispatch('game.replaceShop', [
            'gameId' => $gameId,
            'positions' => [[
                'ruleCode' => 'sword',
                'buyPrice' => 10,
                'sellPrice' => 4,
                'quantity' => 3,
            ]],
            'expectedPositions' => [],
        ])['success']);
        $bought = $this->dispatch('game.applyEconomy', $this->buy($gameId, $characterId, 'buy-1', 1, 1, 2));
        self::assertTrue($bought['success'], json_encode($bought));
        self::assertSame(1, $this->deliveryCount());
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);
        self::assertTrue($this->dispatch('game.applyEconomy', $this->buy($gameId, $characterId, 'buy-1', 1, 1, 2))['success']);
        self::assertSame(1, $this->deliveryCount());
        self::assertSame(80, $this->characterFacade()->get($characterId)->getChoices()['money']);

        $events = $this->gameApplication()->getLocator()->get(IEventContainer::class)->get(IEventManager::class);
        self::assertInstanceOf(IEventManager::class, $events);
        $events->on(GameDeliveryListenerEvent::NAME, new GameDeliveryBoom(), -1);
        $version = $this->characterFacade()->get($characterId)->getActualVersion();
        $again = $this->dispatch('game.applyEconomy', $this->buy($gameId, $characterId, 'buy-2', $version, 2, 1));
        self::assertTrue($again['success'], json_encode($again));
        self::assertSame(70, $this->characterFacade()->get($characterId)->getChoices()['money']);
        self::assertSame(1, $this->deliveryCount());
        $this->runSync($gameId, null, new FakeGameSyncClock(0));
        self::assertSame(2, $this->deliveryCount());
        self::assertSame(70, $this->characterFacade()->get($characterId)->getChoices()['money']);

        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertArrayNotHasKey('characters', $card['data']);
        self::assertArrayNotHasKey('sheets', $card['data']);
    }

    /**
     * Атака и защита дают строки без ключей листа. Поток не везёт чужого NPC.
     *
     * @return void
     */
    public function testStrikeRowsAndNpcScope(): void
    {
        $mechanics = $this->gameApplication()->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        $rollId = $mechanics->add('roll-delivery', 'Бросок', '', '1.0.0');
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('race', 'Human', '', [
                'characteristics' => [[
                    'characteristic_code' => 'strength',
                    'mode' => 'purchased',
                    'base' => ['base' => 3, 'size' => 0],
                    'purchase' => [['cost' => 2, 'value' => ['base' => 4, 'size' => 0]]],
                ]],
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('swing', new RuleVersionBody('language', 'Swing', '', [
                'role' => 'spoken',
            ], [], [], 'needs_work')),
            RuleCommitEntry::put('sword', new RuleVersionBody('item', 'Sword', '', [
                'category' => 'weapon',
                'weapon' => ['weapon_profiles' => [['type' => 'strike']]],
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
                'mechanic_payload' => ['type' => 'roll', 'data' => ['diceCount' => 1, 'dieFaces' => 1]],
            ]], 'needs_work')),
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
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
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
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $world->getId(),
            'rulesRevision' => 2,
            'name' => 'Hero',
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $this->characterFacade()->replaceMigrated($characterId, 'Hero', true, [
            'name' => 'Hero',
            'raceCode' => 'human',
            'abilities' => [],
            'inventory' => [],
            'characteristicPurchases' => [['characteristicCode' => 'strength', 'cost' => 2]],
            'customRules' => [],
            'money' => 0,
            'active' => true,
        ], [
            'abilityLevels' => [],
            'characteristicPurchases' => [[
                'characteristicCode' => 'strength',
                'value' => ['base' => 4, 'size' => 0],
            ]],
        ], 2, 1);
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 2, 1);
        $npc = $this->dispatch('game.createNpc', [
            'gameId' => $gameId,
            'name' => 'Guard',
            'visibility' => ['scope' => 'gm', 'userIds' => [], 'sections' => []],
        ]);
        self::assertTrue($npc['success'], json_encode($npc));
        $gateway = $this->smartTableGateway;
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $repo = new GameNpcRepository($gateway);
        $guard = $repo->getById($npc['data']['npcId']);
        $guardVersion = $guard->getVersion();
        $guardSheet = $guardVersion['sheet'];
        self::assertIsArray($guardSheet);
        $guardBought = $guardSheet['characteristicPurchases'] ?? [];
        self::assertIsArray($guardBought);
        $guardBought[] = ['characteristicCode' => 'stamina', 'value' => ['base' => 4, 'size' => 0]];
        $guardSheet['characteristicPurchases'] = $guardBought;
        if (!array_key_exists('abilityLevels', $guardSheet)) {
            $guardSheet['abilityLevels'] = [];
        }
        $guardVersion['sheet'] = $guardSheet;
        $npc['data']['actualVersion'] = $repo->replaceVersion(
            $guard->getId(),
            $guardVersion,
            $guard->getActualVersion(),
        )->getActualVersion();
        self::assertTrue($this->dispatch('game.startSession', ['gameId' => $gameId])['success']);
        $battle = $this->dispatch('game.startBattle', [
            'gameId' => $gameId,
            'idempotencyKey' => 'b1',
            'participants' => [
                ['type' => 'character', 'id' => $characterId],
                ['type' => 'npc', 'id' => $npc['data']['npcId']],
            ],
        ]);
        $actual = $this->characterFacade()->get($characterId)->getActualVersion();
        self::assertTrue($this->dispatch('game.declareStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 's1',
            'expectedVersion' => 1,
            'attack' => [
                'attacker' => ['type' => 'character', 'id' => $characterId],
                'defender' => ['type' => 'npc', 'id' => $npc['data']['npcId']],
                'actionRuleCode' => 'swing',
                'itemRuleCode' => 'sword',
                'profileType' => 'strike',
                'profileIndex' => 0,
            ],
        ])['success']);
        $resolved = $this->dispatch('game.resolveStrike', [
            'gameId' => $gameId,
            'battleId' => $battle['data']['battleId'],
            'idempotencyKey' => 'd1',
            'expectedVersion' => 2,
            'expectedSheetVersion' => $npc['data']['actualVersion'],
            'defense' => ['reaction' => 'ignore'],
        ]);
        self::assertTrue($resolved['success'], json_encode($resolved));
        self::assertSame($actual, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame(2, $this->deliveryCount());
        $rows = $this->deliveryRows();
        self::assertSame([], $rows[0]['keys']);
        self::assertSame([], $rows[1]['keys']);

        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'pl', 'name' => 'Pl']);
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
                'npcs' => [['npcId' => $npc['data']['npcId'], 'actualVersion' => $resolved['data']['sheetVersion']]],
                'positions' => [],
            ],
        ]);
        self::assertTrue($loot['success'], json_encode($loot));
        $this->setActor($playerId, [GamePermissionKeys::VIEW_ALL]);
        $playerFrames = $this->frames($this->runSync($gameId, 0, new FakeGameSyncClock(0)));
        $flat = json_encode($playerFrames);
        self::assertIsString($flat);
        self::assertStringNotContainsString('"id":' . $npc['data']['npcId'], $flat);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::EDIT_ALL]);
        $ownerFrames = $this->frames($this->runSync($gameId, 0, new FakeGameSyncClock(0)));
        self::assertStringContainsString((string) $npc['data']['npcId'], json_encode($ownerFrames));
    }

    /**
     * Тело покупки.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param string $key Ключ.
     * @param int $actualVersion Версия листа.
     * @param int $shopVersion Версия позиции.
     * @param int $quantity Число.
     *
     * @return array<string, mixed> Тело.
     */
    private function buy(
        int $gameId,
        int $characterId,
        string $key,
        int $actualVersion,
        int $shopVersion,
        int $quantity,
    ): array {
        return [
            'gameId' => $gameId,
            'idempotencyKey' => $key,
            'parts' => [[
                'kind' => 'buy',
                'characterId' => $characterId,
                'ruleCode' => 'sword',
                'quantity' => $quantity,
            ]],
            'expectedVersions' => [
                'characters' => [['characterId' => $characterId, 'actualVersion' => $actualVersion]],
                'npcs' => [],
                'positions' => [['ruleCode' => 'sword', 'version' => $shopVersion]],
            ],
        ];
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
     * Число строк outbox.
     *
     * @return int Число.
     */
    private function deliveryCount(): int
    {
        return count($this->deliveryRows());
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

    /**
     * Один hello и тики часов.
     *
     * @param int $gameId Игра.
     * @param int|null $lastCursor Курсор.
     * @param FakeGameSyncClock $clock Часы.
     *
     * @return string Байты.
     */
    private function runSync(int $gameId, ?int $lastCursor, FakeGameSyncClock $clock): string
    {
        $chunks = '';
        $userAccess = $this->gameApplication()->getLocator()->get(IUserContainer::class)->get(IUserAccess::class);
        self::assertInstanceOf(IUserAccess::class, $userAccess);
        $sync = new GameDeliverySync(
            $userAccess,
            (new GameDeliveryPortFactory())->create($this->gameApplication()->getLocator()),
            $clock,
        );
        $request = $this->createStub(IHttpRequest::class);
        $request->method('getQueryValue')->willReturnCallback(
            static function (string $name) use ($gameId, $lastCursor): mixed {
                if ($name === 'gameId') {
                    return (string) $gameId;
                }

                return $lastCursor === null ? null : (string) $lastCursor;
            },
        );
        $sync->run($request, new SseEmitter(
            static function (string $chunk) use (&$chunks): void {
                $chunks .= $chunk;
            },
            false,
        ));

        return $chunks;
    }

    /**
     * JSON кадров.
     *
     * @param string $chunks Байты.
     *
     * @return array<int, array<string, mixed>> Кадры.
     */
    private function frames(string $chunks): array
    {
        preg_match_all('/event: sync\ndata: ([^\n]+)\n\n/', $chunks, $matches);
        $frames = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $frames[] = $decoded;
            }
        }

        return $frames;
    }
}

/**
 * Имя события для теста. Дубль константы слушателя, чтобы не тянуть Service в имя файла зря.
 */
final class GameDeliveryListenerEvent
{
    public const NAME = 'Roleplay\\Game.Delivery::Recorded';
}

/**
 * Слушатель, который падает после commit.
 */
final class GameDeliveryBoom implements IEventListener
{
    /**
     * Всегда ошибка доставки.
     *
     * @param IEventPayload $payload Факт.
     *
     * @return IEventResult|null Нет.
     *
     * @throws GameInvalidException Сбой.
     */
    public function handle(IEventPayload $payload): ?IEventResult
    {
        unset($payload);

        throw new GameInvalidException('delivery failed');
    }
}
