<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikeConsequences;
use Mifrial\Roleplay\Mechanic\Service\Handler\InjuryEfficiencyHandler;
use Mifrial\Roleplay\Mechanic\Service\Handler\InjuryExtraDiceHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Колющий: ⌊РУ / 2⌋ к сложности автоувечья.
 */
final class GameStrikeConsequencesPiercingTest extends TestCase
{
    /**
     * Маркер даёт половину РУ. Без маркера и при нуле РУ добавки нет.
     *
     * @return void
     */
    public function testInjuryExtraHalvesSuccessRating(): void
    {
        $subject = (new ReflectionClass(GameStrikeConsequences::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GameStrikeConsequences::class, 'getInjuryExtra');
        $method->setAccessible(true);
        $marked = [['handler' => new InjuryExtraDiceHandler(), 'payload' => []]];
        $plain = [['handler' => new InjuryEfficiencyHandler(), 'payload' => ['delta' => -1]]];

        self::assertSame(2, $method->invoke($subject, $marked, 5));
        self::assertSame(0, $method->invoke($subject, $marked, 0));
        self::assertSame(0, $method->invoke($subject, $plain, 5));
    }
}
