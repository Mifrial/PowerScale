<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Service\GameCharacterDiff;
use Mifrial\Roleplay\Game\Service\GameCharacterReview;
use PHPUnit\Framework\TestCase;

final class GameCharacterDiffTest extends TestCase
{
    /**
     * Порядок ключей и wound.heldBy не создают diff. reviewState из маркера и diff.
     *
     * @return void
     */
    public function testDiffReviewAndPointsLimit(): void
    {
        $actual = $this->actual(['b' => 1, 'a' => ['wound' => ['heldBy' => 9, 'mark' => 'cut']]]);
        $snapshot = [
            'name' => 'Hero',
            'rulesRevision' => 1,
            'choices' => ['a' => 1, 'b' => 2],
            'sheet' => ['a' => ['wound' => ['mark' => 'cut', 'heldBy' => 3]], 'b' => 1],
        ];
        $diff = new GameCharacterDiff();
        self::assertFalse($diff->hasChanges($snapshot, $actual));
        self::assertFalse($diff->isChanged($snapshot, $actual));
        $review = new GameCharacterReview($diff, $this->createMock(ICharacterSheets::class), $this->roster());
        self::assertSame('clean', $review->reviewState($snapshot, null, $actual));
        self::assertSame('returned', $review->reviewState($snapshot, DateTime::now(), $actual));
        self::assertSame('changes_pending', $review->reviewState(null, null, $actual));
        $changed = $snapshot;
        $changed['name'] = 'Other';
        self::assertSame('changes_pending', $review->reviewState($changed, null, $actual));
    }

    /**
     * Допуск: diff, return, ревизия и ответ листа. Участник сессии ложен.
     *
     * @return void
     */
    public function testAdmissionPredicates(): void
    {
        $actual = $this->actual([]);
        $snapshot = [
            'name' => 'Hero',
            'rulesRevision' => 1,
            'choices' => ['b' => 2, 'a' => 1],
            'sheet' => [],
        ];
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('acceptsStoredChoices')->willReturn(true);
        $review = new GameCharacterReview(new GameCharacterDiff(), $sheets, $this->roster());
        $row = $this->membership('active', $snapshot, null);
        self::assertFalse($review->needsModeration($snapshot, $actual));
        self::assertTrue($review->needsModeration(null, $actual));
        self::assertTrue($review->canStartSession($this->game(1), $row, $actual));
        self::assertFalse($review->canStartSession($this->game(2), $row, $actual));
        self::assertFalse($review->canStartSession($this->game(1), $this->membership('submitted', $snapshot, null), $actual));
        self::assertFalse($review->needsModeration($snapshot, $actual));
        self::assertFalse($review->canStartSession($this->game(1), $this->membership('active', $snapshot, DateTime::now()), $actual));
        self::assertFalse($review->isActiveSessionParticipant(4, 8));
        $participant = $this->createMock(IGameSessionRoster::class);
        $participant->method('isParticipant')->with(4, 8)->willReturn(true);
        $inSession = new GameCharacterReview(new GameCharacterDiff(), $sheets, $participant);
        self::assertTrue($inSession->isActiveSessionParticipant(4, 8));
        $blockedSheets = $this->createMock(ICharacterSheets::class);
        $blockedSheets->method('acceptsStoredChoices')->willThrowException(new CharacterNotFoundException());
        $blocked = new GameCharacterReview(new GameCharacterDiff(), $blockedSheets, $this->roster());
        self::assertFalse($blocked->canStartSession($this->game(1), $row, $actual));
    }

    /**
     * Лист для diff.
     *
     * @param array<string, mixed> $sheet Кэш.
     *
     * @return CharacterRecord Строка.
     */
    private function actual(array $sheet): CharacterRecord
    {
        return CharacterRecord::fromNormalized([
            'id' => 1,
            'owner_id' => 2,
            'space_id' => 3,
            'rules_revision' => 1,
            'name' => 'Hero',
            'active' => true,
            'actual_version' => 1,
            'choices' => ['b' => 2, 'a' => 1],
            'sheet' => $sheet,
            'visibility_fields' => [],
            'is_public' => false,
            'owner_notes' => '',
            'created_at' => DateTime::now(),
            'updated_at' => DateTime::now(),
        ]);
    }

    /**
     * Состав пуст.
     *
     * @return IGameSessionRoster Чтение.
     */
    private function roster(): IGameSessionRoster
    {
        $roster = $this->createMock(IGameSessionRoster::class);
        $roster->method('isParticipant')->willReturn(false);

        return $roster;
    }

    /**
     * Игра с номером ревизии.
     *
     * @param int $rulesRevision Номер.
     *
     * @return GameRecord Строка.
     */
    private function game(int $rulesRevision): GameRecord
    {
        return GameRecord::fromNormalized([
            'id' => 7,
            'owner_id' => 2,
            'name' => 'Tale',
            'short_description' => '',
            'description' => '',
            'status' => 'draft',
            'visibility' => 'all',
            'join_policy' => 'anyone',
            'space_id' => 3,
            'space_code' => 'world',
            'rules_revision' => $rulesRevision,
            'os_points_limit' => null,
            'ol_points_limit' => null,
            'or_points_limit' => null,
            'money_limit' => null,
            'whitelist' => [],
            'created_at' => DateTime::now(),
            'updated_at' => DateTime::now(),
        ]);
    }

    /**
     * Строка персонажа.
     *
     * @param string $status Статус.
     * @param array<string, mixed>|null $snapshot Копия.
     * @param DateTime|null $returnedAt Маркер.
     *
     * @return GameCharacterRecord Строка.
     */
    private function membership(string $status, ?array $snapshot, ?DateTime $returnedAt): GameCharacterRecord
    {
        return GameCharacterRecord::fromNormalized([
            'id' => 1,
            'game_id' => 7,
            'character_id' => 8,
            'character_owner_id' => 2,
            'status' => $status,
            'approved_character_version' => $snapshot,
            'membership_revision' => 1,
            'returned_at' => $returnedAt,
            'return_reason' => $returnedAt === null ? null : 'back',
            'os_bonus' => 0,
            'or_bonus' => 0,
            'ol_bonus' => 0,
            'section_visibility' => [],
        ]);
    }
}
