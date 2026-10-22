<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Roleplay\Game\Service\GameStrikeChat;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Проверка на увечье: знак текста и форма вложения.
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
        $subject = (new ReflectionClass(GameStrikeChat::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GameStrikeChat::class, 'getMaimText');
        $method->setAccessible(true);

        self::assertSame('Цель получает временное увечье с силой 2.', $method->invoke($subject, 'Цель', -2));
        self::assertSame('Цель не получает увечье.', $method->invoke($subject, 'Цель', 0));
        self::assertSame('Цель не получает увечье.', $method->invoke($subject, 'Цель', 3));
    }

    /**
     * Отрицательный рейтинг даёт общую форму `{injury, damageTypeName}`.
     *
     * @return void
     */
    public function testMaimPayloadShape(): void
    {
        $roll = ['rating' => -2, 'difficulty' => ['base' => 2, 'size' => 0]];

        $payload = $this->payload($roll);

        self::assertSame(2, $payload['details']['injury']['strength']);
        self::assertSame(-2, $payload['details']['injury']['rating']);
        self::assertSame(2, $payload['details']['injury']['difficulty']);
        self::assertSame('Огонь', $payload['details']['damageTypeName']);
        self::assertSame('Цель получает временное увечье с силой 2.', $payload['text']);
    }

    /**
     * Без увечья сила нулевая, но форма вложения та же.
     *
     * @return void
     */
    public function testMaimPayloadWithoutInjury(): void
    {
        $payload = $this->payload(['rating' => 1, 'difficulty' => ['base' => 1, 'size' => 0]]);

        self::assertSame(0, $payload['details']['injury']['strength']);
        self::assertSame('Цель не получает увечье.', $payload['text']);
    }

    /**
     * @param array<string, mixed> $roll Бросок проверки.
     *
     * @return array<string, mixed> Вложение.
     */
    private function payload(array $roll): array
    {
        $subject = (new ReflectionClass(GameStrikeChat::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GameStrikeChat::class, 'getMaimPayload');
        $method->setAccessible(true);

        /** @var array<string, mixed> $payload */
        $payload = $method->invoke($subject, 'Цель', $roll, 'Огонь');

        return $payload;
    }
}
