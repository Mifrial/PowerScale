<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikes;
use Mifrial\Roleplay\Game\Service\GameWideStrikeResults;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Проверка на увечье: знак текста и форма вложения одинаковы в обоих путях.
 */
final class GameStrikeMaimPayloadTest extends TestCase
{
    /**
     * Отрицательный рейтинг — увечье, неотрицательный — увечья нет.
     *
     * @return void
     */
    public function testMaimTextSign(): void
    {
        foreach ([GameStrikes::class, GameWideStrikeResults::class] as $class) {
            $subject = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $method = new ReflectionMethod($class, 'maimText');
            $method->setAccessible(true);

            self::assertSame(
                'Цель получает временное увечье с силой 2.',
                $method->invoke($subject, 'Цель', -2),
                $class,
            );
            self::assertSame('Цель не получает увечье.', $method->invoke($subject, 'Цель', 0), $class);
            self::assertSame('Цель не получает увечье.', $method->invoke($subject, 'Цель', 3), $class);
        }
    }

    /**
     * Отрицательный рейтинг даёт общую форму `{injury, damageTypeName}`.
     *
     * @return void
     */
    public function testMaimPayloadShapeMatches(): void
    {
        $roll = ['rating' => -2, 'difficulty' => ['base' => 2, 'size' => 0]];

        $single = $this->payload(GameStrikes::class, $roll);
        $wide = $this->payload(GameWideStrikeResults::class, $roll);

        self::assertSame($single, $wide);
        self::assertSame(2, $single['details']['injury']['strength']);
        self::assertSame(-2, $single['details']['injury']['rating']);
        self::assertSame(2, $single['details']['injury']['difficulty']);
        self::assertSame('Огонь', $single['details']['damageTypeName']);
        self::assertSame('Цель получает временное увечье с силой 2.', $single['text']);
    }

    /**
     * Без увечья сила нулевая, но форма вложения та же.
     *
     * @return void
     */
    public function testMaimPayloadWithoutInjury(): void
    {
        $roll = ['rating' => 1, 'difficulty' => ['base' => 1, 'size' => 0]];

        $single = $this->payload(GameStrikes::class, $roll);
        $wide = $this->payload(GameWideStrikeResults::class, $roll);

        self::assertSame($single, $wide);
        self::assertSame(0, $single['details']['injury']['strength']);
        self::assertSame('Цель не получает увечье.', $single['text']);
    }

    /**
     * @param class-string $class Класс.
     * @param array<string, mixed> $roll Бросок проверки.
     *
     * @return array<string, mixed> Вложение.
     */
    private function payload(string $class, array $roll): array
    {
        $subject = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($class, 'maimPayload');
        $method->setAccessible(true);

        /** @var array<string, mixed> $payload */
        $payload = $method->invoke($subject, 'Цель', $roll, 'Огонь');

        return $payload;
    }
}
