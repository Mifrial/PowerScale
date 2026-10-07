<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use PHPUnit\Framework\TestCase;

final class GameMemberHttpMysqlTest extends TestCase
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
     * Состав: актор, владелец, edit_all, view_all, игрок.
     *
     * @return void
     */
    public function testMemberHttpAccess(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $gameId = $this->dispatch('game.create', $this->payload($world->getId()))['data']['id'];
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'player', 'name' => 'Player']);
        $this->setActor(null, []);
        $required = $this->dispatch('game.addMember', $this->memberBody($gameId, $playerId, 'player'));
        self::assertSame('AUTH_REQUIRED', $required['error']['code']);
        $strangerId = $this->gameUserAccounts()->addFromInput(['login' => 'stranger', 'name' => 'Stranger']);
        $this->setActor($strangerId, []);
        $foreign = $this->dispatch('game.addMember', $this->memberBody($gameId, $playerId, 'player'));
        self::assertSame('GAME_NOT_FOUND', $foreign['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $added = $this->dispatch('game.addMember', $this->memberBody($gameId, $playerId, 'player'));
        self::assertTrue($added['success']);
        self::assertSame('player', $added['data']['role']);
        self::assertArrayNotHasKey('permissions', $added['data']);
        self::assertArrayNotHasKey('userName', $added['data']);
    }

    /**
     * Чужой список и лишнее поле.
     *
     * @return void
     */
    public function testForeignListAndExtraField(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $gameId = $this->dispatch('game.create', $this->payload($world->getId()))['data']['id'];
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'player', 'name' => 'Player']);
        $this->dispatch('game.addMember', $this->memberBody($gameId, $playerId, 'gm'));
        $this->setActor($playerId, []);
        $hidden = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
        $list = $this->dispatch('game.getMemberList', ['gameId' => $gameId]);
        self::assertSame('GAME_NOT_FOUND', $list['error']['code']);
        $this->setActor($playerId, [GamePermissionKeys::VIEW_ALL]);
        $visible = $this->dispatch('game.getMemberList', ['gameId' => $gameId]);
        self::assertTrue($visible['success']);
        self::assertSame('gm', $visible['data'][0]['role']);
        $this->setActor($playerId, [GamePermissionKeys::EDIT_ALL]);
        $edited = $this->dispatch('game.addMember', $this->memberBody(
            $gameId,
            $this->gameUserAccounts()->addFromInput(['login' => 'extra', 'name' => 'Extra']),
            'player',
        ));
        self::assertTrue($edited['success']);
        $owned = $this->dispatch('game.getMemberList', ['gameId' => $gameId]);
        self::assertCount(2, $owned['data']);
        $extra = $this->dispatch('game.addMember', [
            'gameId' => $gameId,
            'userId' => $playerId,
            'role' => 'player',
            'permissions' => ['game.moderate'],
        ]);
        self::assertSame('INVALID_PARAMS', $extra['error']['code']);
    }

    /**
     * Актор HTTP. null снимает актора.
     *
     * @param int|null $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(?int $userId, array $permissionKeys): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        if ($userId === null) {
            $this->requestContext->setActor(null);

            return;
        }

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
     * Тело create.
     *
     * @param int $spaceId Мир.
     *
     * @return array<string, mixed> JSON.
     */
    private function payload(int $spaceId): array
    {
        return [
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
        ];
    }

    /**
     * Тело участника.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $role Роль.
     *
     * @return array<string, mixed> JSON.
     */
    private function memberBody(int $gameId, int $userId, string $role): array
    {
        return [
            'gameId' => $gameId,
            'userId' => $userId,
            'role' => $role,
        ];
    }
}
