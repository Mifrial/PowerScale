<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Dto\GameMemberPatch;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Service\GamePermissionKeys;
use PHPUnit\Framework\TestCase;

final class GameMemberMysqlTest extends TestCase
{
    use GameMysqlFixture;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->connectGameMysql();
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
     * Роль, список, снятие и запрет владельца.
     *
     * @return void
     */
    public function testAddUpdateListAndRemove(): void
    {
        $gameId = $this->addDraft($this->addWorldWithRevision('razrabotka')->getId());
        $userId = $this->gameUserAccounts()->addFromInput(['login' => 'gm', 'name' => 'Gm']);
        $added = $this->gameFacade()->addMember($this->member($gameId, $userId, 'gm'));
        self::assertSame('gm', $added->getRole());
        $updated = $this->gameFacade()->updateMember($gameId, $userId, GameMemberPatch::fromNormalized([
            'role' => 'player',
        ]));
        self::assertSame('player', $updated->getRole());
        self::assertSame([$userId], $this->userIds($gameId));
        $this->gameFacade()->deleteMember($gameId, $userId);
        self::assertSame([], $this->gameFacade()->getMemberList($gameId));
    }

    /**
     * Владелец, плохая роль, повтор и чужие id.
     *
     * @return void
     */
    public function testRejectsOwnerRoleAndMissingRows(): void
    {
        $gameId = $this->addDraft($this->addWorldWithRevision('razrabotka')->getId());
        $userId = $this->gameUserAccounts()->addFromInput(['login' => 'play', 'name' => 'Play']);
        $this->expectInvalid(fn () => $this->gameFacade()->addMember($this->member($gameId, $this->ownerUserId, 'player')));
        $this->expectInvalid(fn () => $this->gameFacade()->addMember($this->member($gameId, $userId, 'owner')));
        $this->gameFacade()->addMember($this->member($gameId, $userId, 'gm'));
        $this->expectInvalid(fn () => $this->gameFacade()->addMember($this->member($gameId, $userId, 'player')));
        $this->expectNotFound(fn () => $this->gameFacade()->addMember($this->member($gameId, 999, 'player')));
        $this->expectNotFound(fn () => $this->gameFacade()->addMember($this->member(999, $userId, 'player')));
        $this->expectNotFound(fn () => $this->gameFacade()->updateMember($gameId, 999, GameMemberPatch::fromNormalized([
            'role' => 'player',
        ])));
        $this->expectNotFound(fn () => $this->gameFacade()->deleteMember($gameId, 999));
        self::assertSame([$userId], $this->userIds($gameId));
    }

    /**
     * completed не меняет состав. Один user в двух играх.
     *
     * @return void
     */
    public function testCompletedAndTwoGames(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $firstId = $this->addDraft($world->getId());
        $secondId = $this->addDraft($world->getId());
        $userId = $this->gameUserAccounts()->addFromInput(['login' => 'two', 'name' => 'Two']);
        $otherId = $this->gameUserAccounts()->addFromInput(['login' => 'other', 'name' => 'Other']);
        $this->gameFacade()->addMember($this->member($firstId, $userId, 'gm'));
        $this->gameFacade()->addMember($this->member($secondId, $userId, 'player'));
        $this->gameFacade()->addMember($this->member($firstId, $otherId, 'player'));
        self::assertCount(2, $this->gameFacade()->getMemberList($firstId));
        $this->gameFacade()->update($firstId, GamePatch::fromNormalized([
            'name' => 'Tale',
            'status' => 'completed',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'osPointsLimit' => null,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
            'rulesRevision' => 1,
        ]));
        $this->expectInvalid(fn () => $this->gameFacade()->addMember($this->member($firstId, $otherId, 'gm')));
        $this->expectInvalid(fn () => $this->gameFacade()->updateMember($firstId, $userId, GameMemberPatch::fromNormalized([
            'role' => 'player',
        ])));
        $this->expectInvalid(fn () => $this->gameFacade()->deleteMember($firstId, $userId));
        self::assertSame('gm', $this->gameFacade()->getMember($firstId, $userId)->getRole());
        self::assertSame(
            [GamePermissionKeys::EDIT, GamePermissionKeys::MODERATE, GamePermissionKeys::MANAGE],
            GamePermissionKeys::keysFor(true, null),
        );
    }

    /**
     * Черновик владельца.
     *
     * @param int $spaceId Мир.
     *
     * @return int Id.
     */
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

    /**
     * Участник.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $role Роль.
     *
     * @return NewGameMember DTO.
     */
    private function member(int $gameId, int $userId, string $role): NewGameMember
    {
        return NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $userId,
            'role' => $role,
        ]);
    }

    /**
     * Id учёток в списке.
     *
     * @param int $gameId Игра.
     *
     * @return list<int> Id.
     */
    private function userIds(int $gameId): array
    {
        $userIds = [];
        foreach ($this->gameFacade()->getMemberList($gameId) as $record) {
            $userIds[] = $record->getUserId();
        }

        return $userIds;
    }

    /**
     * Ожидает NOT_FOUND.
     *
     * @param callable $action Вызов.
     *
     * @return void
     */
    private function expectNotFound(callable $action): void
    {
        try {
            $action();
            self::fail('missing row must fail');
        } catch (GameNotFoundException $exception) {
            self::assertSame('GAME_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Ожидает INVALID.
     *
     * @param callable $action Вызов.
     *
     * @return void
     */
    private function expectInvalid(callable $action): void
    {
        try {
            $action();
            self::fail('invalid input must fail');
        } catch (GameInvalidException $exception) {
            self::assertSame('GAME_INVALID', $exception->getErrorCode());
        }
    }
}
