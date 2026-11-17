<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellCastDistance;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Дистанция заявки каста: размерная форма и старое целое.
 */
final class GameSpellCastDistanceTest extends TestCase
{
    /**
     * Размерная форма в ипари даёт точное расстояние.
     *
     * @return void
     */
    public function testDimensionalDistanceIsParsed(): void
    {
        $distance = (new GameSpellCastDistance())->value([
            'distance' => [
                'type' => 'ipari',
                'value' => ['type' => 'literal', 'value' => ['base' => 2, 'size' => 1]],
            ],
        ]);

        self::assertSame(2, $distance->getBase());
        self::assertSame(1, $distance->getSize());
        self::assertSame(4, $distance->toInteger());
    }

    /**
     * Узлы dimensional и fixed тоже принимаются.
     *
     * @return void
     */
    public function testNodeDistancesAreParsed(): void
    {
        $reader = new GameSpellCastDistance();
        $dimensional = $reader->value([
            'distance' => ['type' => 'ipari', 'value' => ['type' => 'dimensional', 'base' => 3, 'size' => 0]],
        ]);
        $fixed = $reader->value([
            'distance' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
        ]);

        self::assertSame(3, $dimensional->toInteger());
        self::assertSame(5, $fixed->toInteger());
    }

    /**
     * Старое целое без размерной формы читается как дистанция нулевого размера.
     *
     * @return void
     */
    public function testLegacyIntIsAccepted(): void
    {
        $distance = (new GameSpellCastDistance())->value(['distanceIpari' => 3]);

        self::assertSame(3, $distance->toInteger());
        self::assertSame(0, $distance->getSize());
    }

    /**
     * Оба поля с одной дистанцией допустимы.
     *
     * @return void
     */
    public function testLegacyAndDimensionalMustAgree(): void
    {
        $distance = (new GameSpellCastDistance())->value([
            'distanceIpari' => 4,
            'distance' => [
                'type' => 'ipari',
                'value' => ['type' => 'literal', 'value' => ['base' => 4, 'size' => 0]],
            ],
        ]);

        self::assertSame(4, $distance->toInteger());
    }

    /**
     * Расхождение размерной формы и старого целого — отказ.
     *
     * @return void
     */
    public function testLegacyMismatchIsRejected(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameSpellCastDistance())->value([
            'distanceIpari' => 2,
            'distance' => [
                'type' => 'ipari',
                'value' => ['type' => 'literal', 'value' => ['base' => 4, 'size' => 0]],
            ],
        ]);
    }

    /**
     * Шаги не выражают ипари и отклоняются.
     *
     * @return void
     */
    public function testStepTypeIsRejected(): void
    {
        $this->expectException(GameInvalidException::class);
        (new GameSpellCastDistance())->value([
            'distance' => [
                'type' => 'step',
                'value' => ['type' => 'literal', 'value' => ['base' => 1, 'size' => 0]],
            ],
        ]);
    }

    /**
     * Отрицательная база и лишние ключи ломают форму.
     *
     * @return void
     */
    public function testBrokenShapeIsRejected(): void
    {
        $reader = new GameSpellCastDistance();
        $negative = static fn (): DimensionalNumber => $reader->value([
            'distance' => [
                'type' => 'ipari',
                'value' => ['type' => 'literal', 'value' => ['base' => -1, 'size' => 0]],
            ],
        ]);
        $extra = static fn (): DimensionalNumber => $reader->value([
            'distance' => [
                'type' => 'ipari',
                'value' => ['type' => 'literal', 'value' => ['base' => 1, 'size' => 0, 'scale' => 1]],
            ],
        ]);

        foreach ([$negative, $extra] as $call) {
            try {
                $call();
                self::fail('Ожидался отказ формы дистанции');
            } catch (GameInvalidException) {
                self::assertTrue(true);
            }
        }
    }
}
