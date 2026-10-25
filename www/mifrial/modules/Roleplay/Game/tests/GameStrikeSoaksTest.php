<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikeAmounts;
use Mifrial\Roleplay\Game\Service\GameStrikeSoaks;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет смягчение и вычитание S без MySQL.
 */
final class GameStrikeSoaksTest extends TestCase
{
    /**
     * Пример задачи: урон {3|0}, сопротивление {1|0}, РУ 2, польза −4,
     * Ловкость {5|0} дают S {4|-1} и повреждение {4|-1}.
     *
     * @return void
     */
    public function testExampleSoakAndInjury(): void
    {
        $context = new FormulaContext(['agility' => new CharacteristicNumber(5, 0)]);
        $soak = (new GameStrikeSoaks())->shift($context, 'agility', -4);

        self::assertSame(4, $soak->getBase());
        self::assertSame(-1, $soak->getSize());

        $injury = (new GameStrikeAmounts())->injury(
            new DimensionalNumber(3, 0),
            new DimensionalNumber(1, 0),
            2,
            $soak,
        );

        self::assertSame(4, $injury->getBase());
        self::assertSame(-1, $injury->getSize());
    }

    /**
     * Кулак: урон {4|1}, сопротивление {3|0}, РУ 3, Ловкость {3|0} и польза −3
     * дают S {3|-1} и повреждение {27|-1}.
     *
     * @return void
     */
    public function testFistSoakKeepsHalf(): void
    {
        $context = new FormulaContext(['agility' => new CharacteristicNumber(3, 0)]);
        $soak = (new GameStrikeSoaks())->shift($context, 'agility', -3);

        self::assertSame(3, $soak->getBase());
        self::assertSame(-1, $soak->getSize());

        $injury = (new GameStrikeAmounts())->injury(
            new DimensionalNumber(4, 1),
            new DimensionalNumber(3, 0),
            3,
            $soak,
        );

        self::assertSame(27, $injury->getBase());
        self::assertSame(-1, $injury->getSize());
    }
}
