<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\ReactionSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Узел reaction: когда карточку можно объявить реакцией.
 */
final class ReactionSpecsTest extends TestCase
{
    /**
     * Открытый удар до ответа цели доступен третьей стороне.
     *
     * @return void
     */
    public function testReadsStrikeReaction(): void
    {
        $reaction = ReactionSpecs::read([
            'type' => 'action',
            'reaction' => [
                'trigger' => 'strike',
                'window' => 'before_defense',
                'performer' => 'third_party',
            ],
        ]);

        self::assertNotNull($reaction);
        self::assertSame('strike', $reaction->getTrigger());
        self::assertSame('before_defense', $reaction->getWindow());
        self::assertTrue($reaction->allows('third_party'));
        self::assertFalse($reaction->allows('defender'));
    }

    /**
     * Без узла карточка не реакция.
     *
     * @return void
     */
    public function testAbsentNodeIsNull(): void
    {
        self::assertNull(ReactionSpecs::read(['type' => 'action']));
    }

    /**
     * Неизвестный момент или роль — битая спека.
     *
     * @return void
     */
    public function testRejectsUnknownWindow(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        ReactionSpecs::read([
            'type' => 'action',
            'reaction' => [
                'trigger' => 'strike',
                'window' => 'after_result',
                'performer' => 'third_party',
            ],
        ]);
    }

    /**
     * Неизвестный триггер — битая спека.
     *
     * @return void
     */
    public function testRejectsUnknownTrigger(): void
    {
        $this->expectException(RuleSpecShapeException::class);
        ReactionSpecs::read([
            'type' => 'action',
            'reaction' => [
                'trigger' => 'spell',
                'window' => 'before_defense',
                'performer' => 'third_party',
            ],
        ]);
    }
}
