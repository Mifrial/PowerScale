<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GamePushRules;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GamePushRulesTest extends TestCase
{
    /**
     * Урон толчка сдвигает сырые успехи на один размер, не нормализуя базу.
     *
     * @return void
     */
    public function testDamageShiftsSizeWithoutCharacteristicNormalization(): void
    {
        $rules = (new ReflectionClass(GamePushRules::class))->newInstanceWithoutConstructor();
        self::assertInstanceOf(GamePushRules::class, $rules);

        $damage = $rules->damage(new DimensionalNumber(2, 2));

        self::assertSame(2, $damage->getBase());
        self::assertSame(1, $damage->getSize());
    }
}
