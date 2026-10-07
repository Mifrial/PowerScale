<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use PHPUnit\Framework\TestCase;

final class GameCharacterHttpMysqlTest extends TestCase
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
     * Подача, чужой лист, approve edit_all, view_all, лишнее поле, конфликт.
     *
     * @return void
     */
    public function testSubmitApproveAndDeniedReads(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
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
        ]));
        $characterId = $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => 'Hero',
            'choices' => [],
            'sheet' => [],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'play', 'name' => 'Play']);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $playerId,
            'role' => 'player',
        ]));
        $this->setActor(null, []);
        $anonymous = $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        self::assertSame('AUTH_REQUIRED', $anonymous['error']['code']);
        $this->setActor($playerId, []);
        $foreign = $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        self::assertSame('GAME_NOT_FOUND', $foreign['error']['code']);
        $this->setActor($this->ownerUserId, []);
        $submitted = $this->dispatch('game.submitCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        self::assertTrue($submitted['success']);
        self::assertSame($this->ownerUserId, $submitted['data']['characterOwnerId']);
        $this->setActor($playerId, [GamePermissionKeys::VIEW_ALL]);
        $hidden = $this->dispatch('game.getCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
        ]);
        self::assertSame('GAME_NOT_FOUND', $hidden['error']['code']);
        $this->setActor($playerId, []);
        $denied = $this->dispatch('game.approveCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'actualVersion' => 1,
            'membershipRevision' => 1,
        ]);
        self::assertSame('GAME_NOT_FOUND', $denied['error']['code']);
        $editorId = $this->gameUserAccounts()->addFromInput(['login' => 'ed', 'name' => 'Ed']);
        $this->setActor($editorId, [GamePermissionKeys::EDIT_ALL]);
        $approved = $this->dispatch('game.approveCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'actualVersion' => 1,
            'membershipRevision' => 1,
        ]);
        self::assertTrue($approved['success']);
        self::assertSame('clean', $approved['data']['reviewState']);
        $extra = $this->dispatch('game.returnCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'membershipRevision' => 2,
            'reason' => 'again',
            'returnMessageId' => 1,
        ]);
        self::assertSame('INVALID_PARAMS', $extra['error']['code']);
        $conflict = $this->dispatch('game.approveCharacter', [
            'gameId' => $gameId,
            'characterId' => $characterId,
            'actualVersion' => 1,
            'membershipRevision' => 1,
        ]);
        self::assertSame('GAME_CONFLICT', $conflict['error']['code']);
        self::assertSame('active', $this->membershipFacade()->get($gameId, $characterId)->getStatus());
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
}
