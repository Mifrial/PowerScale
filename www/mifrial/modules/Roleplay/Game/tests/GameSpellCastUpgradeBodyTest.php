<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Service\GameSpellCastUpgradeBody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет разбор списка улучшений каста.
 */
final class GameSpellCastUpgradeBodyTest extends TestCase
{
    /**
     * Поле отсутствует — пустой список.
     *
     * @return void
     */
    public function testEmptyWhenAbsent(): void
    {
        self::assertSame([], (new GameSpellCastUpgradeBody())->parse(null));
        self::assertSame([], (new GameSpellCastUpgradeBody())->parse([]));
    }

    /**
     * Список с value и без value нормализуется.
     *
     * @return void
     */
    public function testNormalizesList(): void
    {
        $parsed = (new GameSpellCastUpgradeBody())->parse([
            ['code' => 'first'],
            ['code' => 'second', 'value' => 2],
        ]);

        self::assertSame([
            ['code' => 'first', 'value' => null],
            ['code' => 'second', 'value' => 2],
        ], $parsed);
    }

    /**
     * Ошибочные формы.
     *
     * @param mixed $raw Значение.
     *
     * @return void
     */
    #[DataProvider('invalidProvider')]
    public function testRejectsInvalid(mixed $raw): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastUpgradeBody())->parse($raw);
    }

    /**
     * Ошибочные формы.
     *
     * @return iterable<string, array{mixed}> Наборы.
     */
    public static function invalidProvider(): iterable
    {
        yield 'map instead of list' => [['upgrade' => ['code' => 'first']]];
        yield 'string item' => [['first']];
        yield 'empty code' => [[['code' => '']]];
        yield 'non-string code' => [[['code' => 1]]];
        yield 'extra key' => [[['code' => 'first', 'value' => 1, 'other' => 2]]];
        yield 'negative value' => [[['code' => 'first', 'value' => -1]]];
        yield 'float value' => [[['code' => 'first', 'value' => 1.5]]];
        yield 'duplicate code' => [[['code' => 'first'], ['code' => 'first']]];
    }
}
