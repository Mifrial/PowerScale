<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikeChat;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Текст проверки истощения широкого удара называет итоговый статус.
 */
final class GameWideStrikeResultsTextTest extends TestCase
{
    /**
     * Все четыре исхода Упадка сил.
     *
     * @return void
     */
    public function testExhaustionTextNamesOutcome(): void
    {
        $subject = (new ReflectionClass(GameStrikeChat::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GameStrikeChat::class, 'getExhaustionText');
        $method->setAccessible(true);

        self::assertSame(
            'Воюн выдерживает истощение (РУ +0) — без Упадка сил.',
            $method->invoke($subject, 'Воюн', 0, 'clear'),
        );
        self::assertSame(
            'Воюн не выдерживает истощение (РУ -1) — Слабость.',
            $method->invoke($subject, 'Воюн', -1, 'weakness'),
        );
        self::assertSame(
            'Воюн не выдерживает истощение (РУ -2) — Обессилен.',
            $method->invoke($subject, 'Воюн', -2, 'disabled'),
        );
        self::assertSame(
            'Воюн не выдерживает истощение (РУ -3) — Потеря сознания.',
            $method->invoke($subject, 'Воюн', -3, 'unconscious'),
        );
    }
}
