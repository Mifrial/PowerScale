<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMemberRepository;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use Mifrial\Messages\Chat\Repository\ChatRepository;
use Mifrial\Messages\Chat\Service\ChatViewAssembler;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\GameMemberPatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Table\GameCharacterTable;
use PHPUnit\Framework\TestCase;

final class GameDonorChatMysqlTest extends TestCase
{
    use GameMysqlFixture;

    private ?IRequestContext $requestContext = null;

    protected function setUp(): void
    {
        $this->connectGameMysql();
        $requestContext = $this->gameApplication()->getLocator()->get(IKernelContainer::class)->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;
    }

    protected function tearDown(): void
    {
        $this->dropGameTables();
    }

    public function testGameChatsAndCharacterReturn(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->addDraft($spaceId);
        $game = $this->gameFacade()->get($gameId);
        $tableId = $game->getGameChatId();
        $discussionId = $game->getDiscussionChatId();
        self::assertNotNull($tableId);
        self::assertNotNull($discussionId);
        self::assertNotSame($tableId, $discussionId);
        self::assertSame('game', $this->chats()->getById($tableId)->getType());
        self::assertSame('game_discussion', $this->chats()->getById($discussionId)->getType());
        self::assertSame([$this->ownerUserId], $this->chats()->getMemberIds($tableId));

        $gmId = $this->gameUserAccounts()->addFromInput(['login' => 'gm', 'name' => 'Gm']);
        $playerId = $this->gameUserAccounts()->addFromInput(['login' => 'pl', 'name' => 'Pl']);
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $gmId,
            'role' => 'gm',
        ]));
        $this->gameFacade()->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $playerId,
            'role' => 'player',
        ]));
        $characterId = $this->addCharacter($spaceId, $playerId);
        $row = $this->membershipFacade()->submit($gameId, $characterId, $playerId);
        $characterChatId = $row->getDiscussionChatId();
        self::assertNotNull($characterChatId);
        $members = $this->chats()->getMemberIds($characterChatId);
        $expectedMembers = [$gmId, $playerId, $this->ownerUserId];
        sort($members);
        sort($expectedMembers);
        self::assertSame($expectedMembers, $members);

        $this->gameFacade()->updateMember($gameId, $gmId, GameMemberPatch::fromNormalized(['role' => 'player']));
        $afterRole = $this->chats()->getMemberIds($characterChatId);
        $expectedAfterRole = [$playerId, $this->ownerUserId];
        sort($afterRole);
        sort($expectedAfterRole);
        self::assertSame($expectedAfterRole, $afterRole);
        self::assertContains($gmId, $this->chats()->getMemberIds($tableId));

        $this->gameFacade()->deleteMember($gameId, $playerId);
        self::assertNotContains($playerId, $this->chats()->getMemberIds($tableId));
        self::assertNotContains($playerId, $this->chats()->getMemberIds($discussionId));
        self::assertContains($playerId, $this->chats()->getMemberIds($characterChatId));

        $editorId = $this->gameUserAccounts()->addFromInput(['login' => 'ed', 'name' => 'Ed']);
        $returned = $this->membershipFacade()->returnToOwner($gameId, $characterId, 1, 'fix', $editorId);
        self::assertNotNull($returned->getReturnMessageId());
        self::assertContains($editorId, $this->chats()->getMemberIds($characterChatId));
        $page = $this->chats()->findMessagePage($characterChatId, $editorId, 10, 0);
        self::assertSame($editorId, $page->getItems()[0]->getUserId());
        self::assertSame($returned->getReturnMessageId(), $page->getItems()[0]->getId());

        $again = $this->membershipFacade()->returnToOwner(
            $gameId,
            $characterId,
            $returned->getMembershipRevision(),
            'more',
            $editorId,
        );
        self::assertNotSame($returned->getReturnMessageId(), $again->getReturnMessageId());
        self::assertSame(2, $this->chats()->findMessagePage($characterChatId, $editorId, 10, 0)->getTotal());

        $this->clearCharacterChat($row->getId());
        $withoutChat = $this->membershipFacade()->returnToOwner(
            $gameId,
            $characterId,
            $again->getMembershipRevision(),
            'plain',
            $editorId,
        );
        self::assertNull($withoutChat->getReturnMessageId());
        self::assertSame('plain', $withoutChat->getReturnReason());
        self::assertSame(2, $this->chats()->findMessagePage($characterChatId, $editorId, 10, 0)->getTotal());

        $this->setActor($this->ownerUserId);
        $inbox = $this->gameApplication()->dispatch('chat.getChats', null)->toArray();
        self::assertTrue($inbox['success']);
        $inboxIds = array_column($inbox['data'], 'id');
        self::assertNotContains($tableId, $inboxIds);
        self::assertNotContains($discussionId, $inboxIds);
        self::assertNotContains($characterChatId, $inboxIds);
        $hostIds = $this->chatView()->hostChatIds($this->chats()->getChatIdsOfUser($this->ownerUserId));
        self::assertNotContains($tableId, $hostIds);
        self::assertNotContains($discussionId, $hostIds);
        self::assertNotContains($characterChatId, $hostIds);
    }

    private function addDraft(int $spaceId): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
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
    }

    private function addCharacter(int $spaceId, int $ownerUserId): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => 'Hero',
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
    }

    private function chats(): IChats
    {
        $chats = $this->gameApplication()->getLocator()->get(IChatContainer::class)->get(IChats::class);
        self::assertInstanceOf(IChats::class, $chats);

        return $chats;
    }

    private function chatView(): ChatViewAssembler
    {
        $gateway = $this->gameApplication()->getLocator()->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);

        return new ChatViewAssembler(
            new ChatRepository($gateway->open(ChatTable::class)->records()),
            new ChatMemberRepository($gateway->open(ChatMemberTable::class)->records()),
            new ChatMessageRepository($gateway->open(ChatMessageTable::class)->records()),
        );
    }

    private function clearCharacterChat(int $rowId): void
    {
        $gateway = $this->gameApplication()->getLocator()->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $gateway);
        $gateway->open(GameCharacterTable::class)->records()->update($rowId, ['discussion_chat_id' => null]);
    }

    /**
     * @param array<int, string> $permissionKeys Ключи.
     */
    private function setActor(int $userId, array $permissionKeys = []): void
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);
        $this->requestContext->setActor(new RequestActor($userId, $permissionKeys, false));
    }
}
