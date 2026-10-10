<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Деление размерной пары на пару.
 */
final class DimensionalNumberTest extends TestCase
{
    /**
     * {7|1} / {3|1} = 2 и остаток {1|1}.
     *
     * @return void
     */
    public function testDividesEqualSizes(): void
    {
        $result = (new DimensionalNumber(7, 1))->divide(new DimensionalNumber(3, 1));

        self::assertSame(2, $result->getQuotient());
        self::assertSame(1, $result->getRemainder()->getBase());
        self::assertSame(1, $result->getRemainder()->getSize());
    }

    /**
     * Разные размеры: сдвиг делителя не нулевой.
     *
     * @return void
     */
    public function testDividesDifferentSizes(): void
    {
        $result = (new DimensionalNumber(7, 0))->divide(new DimensionalNumber(3, 1));

        self::assertSame(1, $result->getQuotient());
        self::assertSame(1, $result->getRemainder()->getBase());
        self::assertSame(0, $result->getRemainder()->getSize());
    }

    /**
     * База делителя 0.
     *
     * @return void
     */
    public function testZeroDivisorIsInvalid(): void
    {
        try {
            (new DimensionalNumber(7, 1))->divide(new DimensionalNumber(0, 1));
            self::fail('zero divisor must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Разность размеров не помещается в int.
     *
     * @return void
     */
    public function testSizeGapBeyondIntIsInvalid(): void
    {
        try {
            (new DimensionalNumber(1, PHP_INT_MAX))->divide(new DimensionalNumber(1, PHP_INT_MIN));
            self::fail('size gap must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * База меньше 0 у делимого или делителя.
     *
     * @return void
     */
    public function testNegativeBaseIsInvalid(): void
    {
        try {
            (new DimensionalNumber(-1, 0))->divide(new DimensionalNumber(1, 0));
            self::fail('negative dividend must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }

        try {
            (new DimensionalNumber(1, 0))->divide(new DimensionalNumber(-1, 0));
            self::fail('negative divisor must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Characteristic modification preserves scale and concrete type.
     *
     * @return void
     */
    public function testCharacteristicModifyPreservesTypeAndScale(): void
    {
        $value = new CharacteristicNumber(5, -1);
        $modified = $value->modify(1);

        self::assertInstanceOf(CharacteristicNumber::class, $modified);
        self::assertSame(3, $modified->getBase());
        self::assertSame(0, $modified->getSize());
        self::assertSame(5, $value->getBase());
        self::assertSame(-1, $value->getSize());
    }

    /**
     * Unbounded modification is invalid.
     *
     * @return void
     */
    public function testUnboundedModifyIsInvalid(): void
    {
        $this->expectException(RuleInvalidException::class);

        (new DimensionalNumber(3, 0))->modify(1);
    }
}
