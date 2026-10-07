<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Exception\GameConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleVersionBody;
use PHPUnit\Framework\TestCase;

final class GameCharacterMysqlTest extends TestCase
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
     * Подача, approve, правка actual, повторный approve, CAS.
     *
     * @return void
     */
    public function testSubmitApproveAndConflict(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->addDraft($spaceId, null);
        $characterId = $this->addCharacter($spaceId, $this->ownerUserId, 'Hero');
        $submitted = $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        self::assertSame('submitted', $submitted->getStatus());
        self::assertNull($submitted->getApprovedCharacterVersion());
        self::assertSame(0, $submitted->getOsBonus());
        self::assertSame(1, $submitted->getMembershipRevision());
        self::assertSame('changes_pending', $submitted->getReviewState());
        self::assertSame($this->ownerUserId, $submitted->getCharacterOwnerId());
        $approved = $this->membershipFacade()->approve($gameId, $characterId, 1, 1);
        self::assertSame('active', $approved->getStatus());
        self::assertSame(2, $approved->getMembershipRevision());
        self::assertNull($approved->getReturnReason());
        self::assertSame('clean', $approved->getReviewState());
        self::assertSame(1, $this->characterFacade()->get($characterId)->getActualVersion());
        $this->characterFacade()->replacePayload($characterId, ['race' => 'elf'], ['hp' => 2], 1);
        self::assertSame('changes_pending', $this->membershipFacade()->get($gameId, $characterId)->getReviewState());
        $again = $this->membershipFacade()->approve($gameId, $characterId, 2, 2);
        self::assertSame('clean', $again->getReviewState());
        self::assertSame(2, $this->characterFacade()->get($characterId)->getActualVersion());
        try {
            $this->membershipFacade()->approve($gameId, $characterId, 1, $again->getMembershipRevision());
            self::fail('stale actual must conflict');
        } catch (GameConflictException $exception) {
            self::assertSame('GAME_CONFLICT', $exception->getErrorCode());
            self::assertSame(2, $exception->getErrorDetails()['currentActualVersion']);
        }

        self::assertSame(2, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame(
            $again->getMembershipRevision(),
            $this->membershipFacade()->get($gameId, $characterId)->getMembershipRevision(),
        );
    }

    /**
     * Два персонажа одного владельца, второй вход, left, чужой user.
     *
     * @return void
     */
    public function testUniquenessLeaveAndOwner(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->addDraft($spaceId, 10);
        $otherGameId = $this->addDraft($spaceId, 10);
        $firstId = $this->addCharacter($spaceId, $this->ownerUserId, 'One');
        $secondId = $this->addCharacter($spaceId, $this->ownerUserId, 'Two');
        $this->membershipFacade()->submit($gameId, $firstId, $this->ownerUserId);
        $this->membershipFacade()->submit($gameId, $secondId, $this->ownerUserId);
        self::assertCount(2, $this->membershipFacade()->getListByGame($gameId, null));
        $this->expectInvalid(fn () => $this->membershipFacade()->submit($otherGameId, $firstId, $this->ownerUserId));
        $strangerId = $this->gameUserAccounts()->addFromInput(['login' => 'str', 'name' => 'Str']);
        $this->expectNotFound(fn () => $this->membershipFacade()->submit($gameId, $firstId, $strangerId));
        $this->expectNotFound(fn () => $this->membershipFacade()->leave($gameId, $firstId, $strangerId, 1));
        $left = $this->membershipFacade()->leave($gameId, $firstId, $this->ownerUserId, 1);
        self::assertSame('left', $left->getStatus());
        $again = $this->membershipFacade()->submit($gameId, $firstId, $this->ownerUserId);
        self::assertSame('submitted', $again->getStatus());
        self::assertSame(
            ['left', 'submitted'],
            $this->statuses($this->membershipFacade()->getListByGame($gameId, null), $firstId),
        );
        $this->membershipFacade()->leave($gameId, $firstId, $this->ownerUserId, $again->getMembershipRevision());
        $moved = $this->membershipFacade()->submit($otherGameId, $firstId, $this->ownerUserId);
        self::assertSame('submitted', $moved->getStatus());
        self::assertSame(
            [$secondId],
            $this->characterIds($this->membershipFacade()->getListByGame($gameId, $this->ownerUserId)),
        );
        self::assertCount(3, $this->membershipFacade()->getListByGame($gameId, null));
    }

    /**
     * Reject, return, бонус и completed.
     *
     * @return void
     */
    public function testRejectReturnBonusAndCompleted(): void
    {
        $spaceId = $this->addWorldWithRevision('razrabotka')->getId();
        $gameId = $this->addDraft($spaceId, null);
        $characterId = $this->addCharacter($spaceId, $this->ownerUserId, 'Hero');
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->expectInvalid(fn () => $this->membershipFacade()->returnToOwner($gameId, $characterId, 1, '  ', $this->ownerUserId));
        $returned = $this->membershipFacade()->returnToOwner($gameId, $characterId, 1, ' fix ', $this->ownerUserId);
        self::assertSame('returned', $returned->getReviewState());
        self::assertSame('fix', $returned->getReturnReason());
        self::assertSame('submitted', $returned->getStatus());
        $this->membershipFacade()->reject($gameId, $characterId, $returned->getMembershipRevision());
        $this->expectNotFound(fn () => $this->membershipFacade()->get($gameId, $characterId));
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $this->membershipFacade()->approve($gameId, $characterId, 1, 1);
        $this->expectInvalid(fn () => $this->membershipFacade()->reject($gameId, $characterId, 2));
        self::assertSame('active', $this->membershipFacade()->get($gameId, $characterId)->getStatus());
        $this->expectInvalid(fn () => $this->membershipFacade()->setBonus($gameId, $characterId, 2, -1, 0, 0));
        $bonus = $this->membershipFacade()->setBonus($gameId, $characterId, 2, 2, 0, 0);
        self::assertSame(2, $bonus->getOsBonus());
        self::assertNull($this->membershipFacade()->getPointsLimit(null, 2));
        self::assertSame(12, $this->membershipFacade()->getPointsLimit(10, 2));
        $this->gameFacade()->update($gameId, GamePatch::fromNormalized([
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
        $this->expectInvalid(fn () => $this->membershipFacade()->setBonus($gameId, $characterId, $bonus->getMembershipRevision(), 1, 0, 0));
        self::assertSame(2, $this->membershipFacade()->get($gameId, $characterId)->getOsBonus());
    }

    /**
     * Персонаж.
     *
     * @param int $spaceId Мир.
     * @param int $ownerUserId Владелец.
     * @param string $name Имя.
     *
     * @return int Id.
     */
    private function addCharacter(int $spaceId, int $ownerUserId, string $name): int
    {
        return $this->characterFacade()->add(NewCharacter::fromNormalized([
            'ownerUserId' => $ownerUserId,
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'name' => $name,
            'choices' => ['race' => 'human'],
            'sheet' => ['hp' => 1],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => true,
        ]));
    }

    /**
     * Черновик.
     *
     * @param int $spaceId Мир.
     * @param int|null $osPointsLimit Потолок ОС.
     *
     * @return int Id.
     */
    private function addDraft(int $spaceId, ?int $osPointsLimit): int
    {
        return $this->gameFacade()->add(NewGame::fromNormalized([
            'ownerUserId' => $this->ownerUserId,
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'spaceId' => $spaceId,
            'rulesRevision' => 1,
            'osPointsLimit' => $osPointsLimit,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => 5,
        ]));
    }

    /**
     * Id персонажей.
     *
     * @param array<int, \Mifrial\Roleplay\Game\Dto\GameCharacterRecord> $rows Строки.
     *
     * @return list<int> Id.
     */
    private function characterIds(array $rows): array
    {
        $characterIds = [];
        foreach ($rows as $row) {
            if ($row->getStatus() !== 'left') {
                $characterIds[] = $row->getCharacterId();
            }
        }

        return $characterIds;
    }

    /**
     * Смена ревизии игры не пишет лист. Сдвиг actual — replaceMigrated.
     *
     * @return void
     */
    public function testRevisionChangeLeavesActualUntilReplaceMigrated(): void
    {
        $world = $this->addWorldWithRevision('razrabotka');
        $this->gameRuleSpaces()->commit($world->getId(), [
            RuleCommitEntry::put('human', new RuleVersionBody('ability', 'Human 2', '', [], [], [], 'needs_work')),
        ]);
        $gameId = $this->addDraft($world->getId(), 10);
        $characterId = $this->addCharacter($world->getId(), $this->ownerUserId, 'Hero');
        $this->membershipFacade()->submit($gameId, $characterId, $this->ownerUserId);
        $approved = $this->membershipFacade()->approve($gameId, $characterId, 1, 1);
        $this->membershipFacade()->setBonus($gameId, $characterId, $approved->getMembershipRevision(), 2, 0, 0);
        $this->gameFacade()->update($gameId, GamePatch::fromNormalized([
            'name' => 'Tale',
            'status' => 'draft',
            'visibility' => 'all',
            'joinPolicy' => 'anyone',
            'osPointsLimit' => 10,
            'olPointsLimit' => null,
            'orPointsLimit' => null,
            'moneyLimit' => null,
            'rulesRevision' => 2,
        ]));
        $shifted = $this->membershipFacade()->get($gameId, $characterId);
        self::assertSame(1, $this->characterFacade()->get($characterId)->getRulesRevision());
        self::assertSame(1, $this->characterFacade()->get($characterId)->getActualVersion());
        self::assertSame(1, $shifted->getApprovedCharacterVersion()['rulesRevision']);
        self::assertFalse($shifted->needsModeration());
        self::assertFalse($shifted->canStartSession());
        self::assertFalse($shifted->isActiveSessionParticipant());
        self::assertSame(2, $shifted->getOsBonus());
        self::assertSame(10, $this->gameFacade()->get($gameId)->getOsPointsLimit());
        $this->characterFacade()->replaceMigrated($characterId, 'Hero', true, ['race' => 'human'], ['hp' => 1], 2, 1);
        $pending = $this->membershipFacade()->get($gameId, $characterId);
        self::assertTrue($pending->needsModeration());
        self::assertSame('changes_pending', $pending->getReviewState());
        self::assertSame(1, $pending->getApprovedCharacterVersion()['rulesRevision']);
        self::assertFalse($pending->isActiveSessionParticipant());
        $again = $this->membershipFacade()->approve(
            $gameId,
            $characterId,
            2,
            $pending->getMembershipRevision(),
        );
        self::assertSame('clean', $again->getReviewState());
        self::assertFalse($again->needsModeration());
        self::assertFalse($again->canStartSession());
        self::assertSame(2, $again->getApprovedCharacterVersion()['rulesRevision']);
        $returned = $this->membershipFacade()->returnToOwner(
            $gameId,
            $characterId,
            $again->getMembershipRevision(),
            'back',
            $this->ownerUserId,
        );
        self::assertFalse($returned->needsModeration());
        self::assertFalse($returned->canStartSession());
        self::assertSame(2, $returned->getOsBonus());
        self::assertNotNull($returned->getReturnMessageId());
        self::assertSame(10, $this->gameFacade()->get($gameId)->getOsPointsLimit());
        $cleared = $this->membershipFacade()->approve(
            $gameId,
            $characterId,
            2,
            $returned->getMembershipRevision(),
        );
        self::assertNull($cleared->getReturnReason());
        self::assertNull($cleared->getReturnMessageId());
    }

    /**
     * Статусы одного персонажа в списке, по порядку id.
     *
     * @param array<int, \Mifrial\Roleplay\Game\Dto\GameCharacterRecord> $rows Строки.
     * @param int $characterId Персонаж.
     *
     * @return list<string> Статусы.
     */
    private function statuses(array $rows, int $characterId): array
    {
        $statuses = [];
        foreach ($rows as $row) {
            if ($row->getCharacterId() === $characterId) {
                $statuses[] = $row->getStatus();
            }
        }

        return $statuses;
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
