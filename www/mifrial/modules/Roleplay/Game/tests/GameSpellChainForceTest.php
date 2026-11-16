<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameSpellChainForce;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет трату остатка успеха каста на Сопротивление магии.
 */
final class GameSpellChainForceTest extends TestCase
{
    /**
     * Сопротивление цели уменьшает остаток успеха.
     *
     * @param int $rating Успех.
     * @param int $resistance Сопротивление.
     * @param int $penetration Пробитие.
     * @param int $left Ожидаемый остаток.
     * @param int $spend Ожидаемая трата.
     * @param bool $avoided Ожидаемый промах.
     *
     * @return void
     */
    #[DataProvider('provider')]
    public function testSpendsRating(
        int $rating,
        int $resistance,
        int $penetration,
        int $left,
        int $spend,
        bool $avoided,
    ): void {
        $result = (new GameSpellChainForce())->spend($rating, $resistance, $penetration);

        self::assertSame($left, $result['rating']);
        self::assertSame($spend, $result['spend']);
        self::assertSame($avoided, $result['avoided']);
    }

    /**
     * Наборы успеха и сопротивления.
     *
     * @return iterable<string, array{int, int, int, int, int, bool}> Наборы.
     */
    public static function provider(): iterable
    {
        yield 'no resistance' => [4, 0, 0, 4, 0, false];
        yield 'exact spend' => [4, 4, 0, 0, 4, false];
        yield 'below zero' => [3, 5, 0, -2, 5, true];
        yield 'penetration' => [3, 5, 2, 0, 3, false];
        yield 'penetration above' => [3, 2, 5, 3, 0, false];
        yield 'negative penetration' => [3, 2, -3, 1, 2, false];
    }
}
