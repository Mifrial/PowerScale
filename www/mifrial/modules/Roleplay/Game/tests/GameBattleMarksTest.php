<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameBattleMarks;
use PHPUnit\Framework\TestCase;

/**
 * Метки боя: наложение, чтение защитой, расход и срок.
 */
final class GameBattleMarksTest extends TestCase
{
    /**
     * Повторная метка того же ключа обновляет, другая цель добавляется.
     *
     * @return void
     */
    public function testUpsertRefreshesSameKey(): void
    {
        $marks = new GameBattleMarks();
        $rows = $marks->upsert([], $this->mark(-2));
        $rows = $marks->upsert($rows, $this->mark(-3));

        self::assertCount(1, $rows);
        self::assertSame(-3, $rows[0]['value'] ?? null);
        self::assertCount(2, $marks->upsert($rows, $this->mark(-2, 8)));
    }

    /**
     * Защита получает дельту и подпись только от нужного атакующего.
     *
     * @return void
     */
    public function testForDefenseMapsMatchingMark(): void
    {
        $marks = new GameBattleMarks();
        $rows = $marks->upsert([], $this->mark(-2));

        $matched = $marks->forDefense(
            $rows,
            ['type' => 'character', 'id' => 12],
            ['type' => 'character', 'id' => 7],
        );
        self::assertCount(1, $matched['advantages']);
        self::assertSame(-2, $matched['advantages'][0]->getDelta());
        self::assertSame('circumstances', $matched['advantages'][0]->getSourceCode());
        self::assertSame('Обстоятельства', $matched['advantages'][0]->getSourceLabel());
        self::assertCount(1, $matched['keys']);

        $otherAttacker = $marks->forDefense(
            $rows,
            ['type' => 'character', 'id' => 12],
            ['type' => 'character', 'id' => 9],
        );
        self::assertSame([], $otherAttacker['advantages']);
        self::assertSame([], $otherAttacker['keys']);

        $otherSubject = $marks->forDefense(
            $rows,
            ['type' => 'character', 'id' => 13],
            ['type' => 'character', 'id' => 7],
        );
        self::assertSame([], $otherSubject['advantages']);
    }

    /**
     * Расход снимает только названные метки и повторяется безопасно.
     *
     * @return void
     */
    public function testConsumeRemovesOnlyKeys(): void
    {
        $marks = new GameBattleMarks();
        $rows = $marks->upsert($marks->upsert([], $this->mark(-2)), $this->mark(-2, 8));
        $keys = $marks->forDefense(
            $rows,
            ['type' => 'character', 'id' => 12],
            ['type' => 'character', 'id' => 7],
        )['keys'];

        $after = $marks->consume($rows, $keys);
        self::assertCount(1, $after);
        self::assertSame(8, $after[0]['scope']['defense_against']['id'] ?? null);
        self::assertSame($after, $marks->consume($after, $keys));
    }

    /**
     * Срок снимает метки актора, чей ход закончился.
     *
     * @return void
     */
    public function testExpireTurnRemovesActorMarks(): void
    {
        $marks = new GameBattleMarks();
        $rows = $marks->upsert([], $this->mark(-2));
        $rows = $marks->upsert($rows, $this->mark(-2, 7, 8));

        $after = $marks->expireTurn($rows, ['type' => 'character', 'id' => 7]);
        self::assertCount(1, $after);
        self::assertSame(8, $after[0]['expires']['of']['id'] ?? null);
    }

    /**
     * Чужая форма метки отклоняется.
     *
     * @return void
     */
    public function testInvalidMarkRejected(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameBattleMarks())->upsert([], $this->mark(-2, 7, 7, 'unknown'));
    }

    /**
     * Метка для проверки.
     *
     * @param int $value Дельта.
     * @param int $qualified Против кого.
     * @param int $expires Кому истекает.
     * @param string $kind Вид.
     *
     * @return array<string, mixed> Метка.
     */
    private function mark(int $value, int $qualified = 7, int $expires = 7, string $kind = 'advantage'): array
    {
        return [
            'subject' => ['type' => 'character', 'id' => 12],
            'kind' => $kind,
            'value' => $value,
            'source_code' => 'circumstances',
            'source_rule_code' => 'obman',
            'scope' => ['defense_against' => ['type' => 'character', 'id' => $qualified]],
            'consumes' => 'first_defense',
            'expires' => ['kind' => 'turn', 'of' => ['type' => 'character', 'id' => $expires]],
        ];
    }
}
