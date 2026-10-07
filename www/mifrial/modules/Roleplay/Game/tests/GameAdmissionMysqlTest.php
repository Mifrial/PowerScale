<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use PHPUnit\Framework\TestCase;

final class GameAdmissionMysqlTest extends TestCase
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
     * Фильтр карточки и список.
     *
     * @return void
     */
    public function testCardFilter(): void
    {
        $worldId = $this->addWorldWithRevision('razrabotka')->getId();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $draftId = $this->createGame($worldId, 'draft', 'all', 'anyone');
        $openId = $this->createGame($worldId, 'recruiting', 'all', 'anyone');
        $strangerId = $this->gameUserAccounts()->addFromInput(['login' => 'see', 'name' => 'See']);
        $this->setActor($strangerId, []);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.get', ['id' => $draftId])['error']['code']);
        $open = $this->dispatch('game.get', ['id' => $openId]);
        self::assertTrue($open['success']);
        self::assertArrayNotHasKey('whitelist', $open['data']);
        $ids = array_column($this->dispatch('game.getList', [])['data'], 'id');
        self::assertNotContains($draftId, $ids);
        self::assertContains($openId, $ids);
        $this->setActor($strangerId, [GamePermissionKeys::VIEW_ALL]);
        self::assertTrue($this->dispatch('game.get', ['id' => $draftId])['success']);
    }

    /**
     * Заявка anyone и отказ чужих policy.
     *
     * @return void
     */
    public function testJoinRequestAndInvite(): void
    {
        $worldId = $this->addWorldWithRevision('razrabotka')->getId();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $gameId = $this->createGame($worldId, 'recruiting', 'all', 'anyone');
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'ask', 'name' => 'Ask']);
        $this->setActor($playerId, []);
        $requested = $this->dispatch('game.requestJoin', ['gameId' => $gameId]);
        self::assertTrue($requested['success']);
        self::assertSame('pending', $requested['data']['status']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $accepted = $this->dispatch('game.respondJoinRequest', [
            'gameId' => $gameId,
            'userId' => $playerId,
            'action' => 'accept',
        ]);
        self::assertSame('accepted', $accepted['data']['status']);
        self::assertSame('player', $this->gameFacade()->getMember($gameId, $playerId)->getRole());
        self::assertNotContains($this->ownerUserId, $this->userIdsOf($gameId));
        $inviteOnly = $this->createGame($worldId, 'recruiting', 'all', 'invite_only');
        $this->setActor($playerId, []);
        self::assertSame('GAME_INVALID', $this->dispatch('game.requestJoin', ['gameId' => $inviteOnly])['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $invited = $this->dispatch('game.invite', ['gameId' => $inviteOnly, 'inviteeId' => $playerId]);
        self::assertTrue($invited['success']);
        $this->setActor($playerId, []);
        $joined = $this->dispatch('game.respondInvitation', [
            'invitationId' => $invited['data']['id'],
            'action' => 'accept',
        ]);
        self::assertSame('accepted', $joined['data']['status']);
        self::assertSame('player', $this->gameFacade()->getMember($inviteOnly, $playerId)->getRole());
    }

    /**
     * Whitelist, friends и completed.
     *
     * @return void
     */
    public function testWhitelistFriendsAndCompleted(): void
    {
        $worldId = $this->addWorldWithRevision('razrabotka')->getId();
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $gameId = $this->createGame($worldId, 'recruiting', 'whitelist', 'whitelist');
        $listedId = $this->gameUserAccounts()->addFromInput(['login' => 'list', 'name' => 'List']);
        $otherId = $this->gameUserAccounts()->addFromInput(['login' => 'out', 'name' => 'Out']);
        $gmId = $this->gameUserAccounts()->addFromInput(['login' => 'lead', 'name' => 'Lead']);
        $this->gameFacade()->addMember($this->memberOf($gameId, $gmId));
        $saved = $this->dispatch('game.setWhitelist', ['gameId' => $gameId, 'userIds' => [$listedId]]);
        self::assertSame([$listedId], $saved['data']['userIds']);
        $card = $this->dispatch('game.get', ['id' => $gameId]);
        self::assertArrayNotHasKey('whitelist', $card['data']);
        $this->setActor($gmId, []);
        self::assertSame([$listedId], $this->dispatch('game.getWhitelist', ['gameId' => $gameId])['data']['userIds']);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.setWhitelist', [
            'gameId' => $gameId,
            'userIds' => [],
        ])['error']['code']);
        self::assertSame([$listedId], $this->gameFacade()->get($gameId)->getWhitelist());
        $this->setActor($otherId, []);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.get', ['id' => $gameId])['error']['code']);
        $this->setActor($listedId, []);
        self::assertTrue($this->dispatch('game.get', ['id' => $gameId])['success']);
        $joined = $this->dispatch('game.joinWhitelist', ['gameId' => $gameId]);
        self::assertSame('player', $joined['data']['role']);
        self::assertSame('GAME_INVALID', $this->dispatch('game.requestJoin', ['gameId' => $gameId])['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE]);
        $friendsId = $this->createGame($worldId, 'recruiting', 'friends', 'friends');
        $this->setActor($otherId, []);
        self::assertSame('GAME_NOT_FOUND', $this->dispatch('game.get', ['id' => $friendsId])['error']['code']);
        self::assertSame('GAME_INVALID', $this->dispatch('game.requestJoin', ['gameId' => $friendsId])['error']['code']);
        $this->setActor($this->ownerUserId, [GamePermissionKeys::CREATE, GamePermissionKeys::EDIT_ALL]);
        $this->dispatch('game.update', [
            'id' => $gameId,
            'name' => 'Tale',
            'shortDescription' => '',
            'description' => '',
            'status' => 'completed',
            'visibility' => 'whitelist',
            'joinPolicy' => 'whitelist',
            'spaceId' => $worldId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]);
        self::assertSame('GAME_INVALID', $this->dispatch('game.setWhitelist', [
            'gameId' => $gameId,
            'userIds' => [$listedId],
        ])['error']['code']);
    }

    /**
     * Создаёт игру.
     *
     * @param int $worldId Мир.
     * @param string $status Статус.
     * @param string $visibility Видимость.
     * @param string $joinPolicy Политика.
     *
     * @return int Id.
     */
    private function createGame(int $worldId, string $status, string $visibility, string $joinPolicy): int
    {
        $created = $this->dispatch('game.create', [
            'name' => 'Tale',
            'shortDescription' => '',
            'description' => '',
            'status' => $status,
            'visibility' => $visibility,
            'joinPolicy' => $joinPolicy,
            'spaceId' => $worldId,
            'rulesRevision' => 1,
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
        ]);
        self::assertTrue($created['success']);

        return $created['data']['id'];
    }

    /**
     * Участник gm.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return \Mifrial\Roleplay\Game\Dto\NewGameMember Строка.
     */
    private function memberOf(int $gameId, int $userId): \Mifrial\Roleplay\Game\Dto\NewGameMember
    {
        return \Mifrial\Roleplay\Game\Dto\NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $userId,
            'role' => 'gm',
        ]);
    }

    /**
     * Id участников.
     *
     * @param int $gameId Игра.
     *
     * @return list<int> Id.
     */
    private function userIdsOf(int $gameId): array
    {
        $ids = [];
        foreach ($this->gameFacade()->getMemberList($gameId) as $member) {
            $ids[] = $member->getUserId();
        }

        return $ids;
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
