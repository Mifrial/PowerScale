<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameHttpMysqlTest extends TestCase
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
     * Ключи, владелец, sessionRunning и отказ чужому.
     *
     * @return void
     */
    public function testCreateGetAndForeignAccess(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $denied = $this->dispatch('game.create', $this->payload($world->getId()));
        self::assertFalse($denied['success']);
        self::assertSame('AUTH_REQUIRED', $denied['error']['code']);
        $this->setActor($this->ownerUserId, []);
        $noKey = $this->dispatch('game.create', $this->payload($world->getId()));
        self::assertSame('AUTH_DENIED', $noKey['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $created = $this->dispatch('game.create', $this->payload($world->getId()));
        self::assertTrue($created['success']);
        self::assertSame($this->ownerUserId, $created['data']['ownerId']);
        self::assertFalse($created['data']['sessionRunning']);
        self::assertIsInt($created['data']['gameChatId']);
        self::assertIsInt($created['data']['discussionChatId']);
        self::assertNotSame($created['data']['gameChatId'], $created['data']['discussionChatId']);
        self::assertSame('razrabotka', $created['data']['spaceCode']);
        $gameId = $created['data']['id'];
        $mismatch = $this->dispatch('game.create', $this->payload($world->getId(), ['spaceCode' => 'other']));
        self::assertSame('GAME_INVALID', $mismatch['error']['code']);
        $otherUserId = $this->gameUserAccounts()->addFromInput(['login' => 'bob', 'name' => 'Bob']);
        $this->setActor($otherUserId, []);
        $hidden = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
        $this->setActor($otherUserId, [GamePermissionKeys::VIEW_ALL]);
        $visible = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertTrue($visible['success']);
        self::assertSame($gameId, $visible['data']['id']);
    }

    /**
     * Update владельца, чужого, edit_all и чужого мира.
     *
     * @return void
     */
    public function testUpdateOwnerForeignAndWorld(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $other = $this->addWorldWithRevision('second');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $created = $this->dispatch('game.create', $this->payload($world->getId()));
        $gameId = $created['data']['id'];
        $updated = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'status' => 'paused',
        ]));
        self::assertTrue($updated['success']);
        self::assertSame('paused', $updated['data']['status']);
        $moved = $this->dispatch('game.update', $this->payload($other->getId(), [
            'id' => $gameId,
            'rulesRevision' => 1,
        ]));
        self::assertSame('GAME_INVALID', $moved['error']['code']);
        self::assertSame($world->getId(), $this->gameFacade()->get($gameId)->getSpaceId());
        $strangerId = $this->gameUserAccounts()->addFromInput(['login' => 'stranger', 'name' => 'Stranger']);
        $this->setActor($strangerId, []);
        $foreign = $this->dispatch('game.update', $this->payload($world->getId(), ['id' => $gameId]));
        self::assertSame('GAME_NOT_FOUND', $foreign['error']['code']);
        $this->setActor($strangerId, [GamePermissionKeys::EDIT_ALL]);
        $edited = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'name' => 'Edited',
        ]));
        self::assertTrue($edited['success']);
        self::assertSame('Edited', $edited['data']['name']);
        $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'status' => 'completed',
        ]));
        $after = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'name' => 'Nope',
        ]));
        self::assertSame('GAME_INVALID', $after['error']['code']);
        $extra = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'sessionRunning' => true,
        ]));
        self::assertSame('INVALID_PARAMS', $extra['error']['code']);
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

    /**
     * Новый номер того же мира пишется. Чужой мир, gm и сессия — нет.
     *
     * @return void
     */
    public function testUpdateRulesRevision(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('ability', 'Human 2', '', [], [], [], 'needs_work')),
        ]);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $created = $this->dispatch('game.create', $this->payload($world->getId()));
        $gameId = $created['data']['id'];
        $moved = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'rulesRevision' => 2,
        ]));
        self::assertTrue($moved['success']);
        self::assertSame(2, $moved['data']['rulesRevision']);
        self::assertFalse($moved['data']['sessionRunning']);
        self::assertSame($world->getId(), $moved['data']['spaceId']);
        $missing = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'rulesRevision' => 9,
        ]));
        self::assertSame('GAME_NOT_FOUND', $missing['error']['code']);
        self::assertSame(2, $this->gameFacade()->get($gameId)->getRulesRevision());
        $code = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'rulesRevision' => 2,
            'spaceCode' => 'other',
        ]));
        self::assertSame('GAME_INVALID', $code['error']['code']);
        $gmId = $this->gameUserAccounts()->addFromInput(['login' => 'gm', 'name' => 'Gm']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $this->dispatch('game.addMember', ['gameId' => $gameId, 'userId' => $gmId, 'role' => 'gm']);
        $this->setActor($gmId, []);
        $denied = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'rulesRevision' => 1,
        ]));
        self::assertSame('GAME_NOT_FOUND', $denied['error']['code']);
        $editorId = $this->gameUserAccounts()->addFromInput(['login' => 'ed2', 'name' => 'Ed']);
        $this->setActor($editorId, [GamePermissionKeys::EDIT_ALL]);
        $edited = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'rulesRevision' => 1,
        ]));
        self::assertTrue($edited['success']);
        self::assertSame(1, $edited['data']['rulesRevision']);
        $this->gameRuleSpaces()->deactivate($world->getId());
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $same = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'name' => 'Still',
        ]));
        self::assertTrue($same['success']);
        self::assertSame('Still', $same['data']['name']);
        $inactive = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'rulesRevision' => 2,
        ]));
        self::assertSame('GAME_INVALID', $inactive['error']['code']);
        self::assertSame(1, $this->gameFacade()->get($gameId)->getRulesRevision());
        $completed = $this->gameRuleSpaces()->get($world->getId());
        self::assertFalse($completed->isActive());
        $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'status' => 'completed',
        ]));
        $after = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'name' => 'Nope',
        ]));
        self::assertSame('GAME_INVALID', $after['error']['code']);
        $extra = $this->dispatch('game.update', $this->payload($world->getId(), [
            'id' => $gameId,
            'sessionRunning' => true,
        ]));
        self::assertSame('INVALID_PARAMS', $extra['error']['code']);
    }

    /**
     * Тело create/update.
     *
     * @param int $spaceId Мир.
     * @param array<string, mixed> $overrides Поля.
     *
     * @return array<string, mixed> JSON.
     */
    private function payload(int $spaceId, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ], $overrides);
    }
}
