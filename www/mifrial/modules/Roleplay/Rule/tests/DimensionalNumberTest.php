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
     * Одинаковые размеры складываются как есть.
     *
     * @return void
     */
    public function testAddsEqualSizes(): void
    {
        $sum = (new DimensionalNumber(2, 0))->add(new DimensionalNumber(3, 0));

        self::assertSame(5, $sum->getBase());
        self::assertSame(0, $sum->getSize());
    }

    /**
     * Больший размер приводится к меньшему: {1|1} + {10|0} = {12|0}.
     *
     * @return void
     */
    public function testAddsDifferentSizes(): void
    {
        $sum = (new DimensionalNumber(1, 1))->add(new DimensionalNumber(10, 0));

        self::assertSame(12, $sum->getBase());
        self::assertSame(0, $sum->getSize());

        $reversed = (new DimensionalNumber(10, 0))->add(new DimensionalNumber(1, 1));

        self::assertSame(12, $reversed->getBase());
        self::assertSame(0, $reversed->getSize());
    }

    /**
     * Отрицательные базы складываются без ограничений.
     *
     * @return void
     */
    public function testAddsNegativeBases(): void
    {
        $sum = (new DimensionalNumber(-2, 0))->add(new DimensionalNumber(5, 0));

        self::assertSame(3, $sum->getBase());
        self::assertSame(0, $sum->getSize());
    }

    /**
     * Число со шкалой базы складывать нельзя.
     *
     * @return void
     */
    public function testAddRejectsBoundedNumber(): void
    {
        try {
            (new DimensionalNumber(3, 0, 3, 5))->add(new DimensionalNumber(1, 0));
            self::fail('bounded number must fail');
        } catch (RuleInvalidException $exception) {
            self::assertSame('RULE_INVALID', $exception->getErrorCode());
        }

        try {
            (new DimensionalNumber(1, 0))->add(new DimensionalNumber(3, 0, 3, 5));
            self::fail('bounded number must fail');
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
     * Размер задаётся напрямую, база сохраняется.
     *
     * @return void
     */
    public function testWithSizeChangesOrdinaryNumber(): void
    {
        $down = (new DimensionalNumber(3, 0))->withSize(-1);
        $up = (new DimensionalNumber(7, 1))->withSize(0);

        self::assertSame(3, $down->getBase());
        self::assertSame(-1, $down->getSize());
        self::assertSame(7, $up->getBase());
        self::assertSame(0, $up->getSize());
    }

    /**
     * Размерное умножение перемножает базы и складывает размеры.
     *
     * @return void
     */
    public function testMultipliesDimensionalNumbers(): void
    {
        $product = (new DimensionalNumber(3, 1))->multiply(new DimensionalNumber(2, 1));

        self::assertSame(6, $product->getBase());
        self::assertSame(2, $product->getSize());
    }

    /**
     * Разность приводится к меньшему размеру.
     *
     * @return void
     */
    public function testSubtractsDimensionalNumbers(): void
    {
        $difference = (new DimensionalNumber(7, 1))->subtract(new DimensionalNumber(3, 0));

        self::assertSame(11, $difference->getBase());
        self::assertSame(0, $difference->getSize());
    }

    /**
     * У характеристик withSize запрещён: размер шага — 3 пункта, не двоичный.
     *
     * @return void
     */
    public function testCharacteristicWithSizeIsRejected(): void
    {
        $this->expectException(RuleInvalidException::class);

        (new CharacteristicNumber(5, 0))->withSize(1);
    }

    /**
     * Характеристики сравниваются по base + 3×size.
     *
     * @return void
     */
    public function testCharacteristicCompareUsesCharacteristicScale(): void
    {
        self::assertSame(
            -1,
            (new CharacteristicNumber(5, 0))->characteristicCompare(new CharacteristicNumber(3, 1)),
        );
        self::assertSame(
            0,
            (new CharacteristicNumber(3, 1))->characteristicCompare(new CharacteristicNumber(6, 0)),
        );
        self::assertSame(
            1,
            (new CharacteristicNumber(4, 0))->characteristicCompare(new CharacteristicNumber(3, 0)),
        );
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
