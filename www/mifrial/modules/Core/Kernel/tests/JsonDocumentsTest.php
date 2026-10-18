<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Http\JsonDocuments;
use Mifrial\Core\Kernel\Value\JsonObject;
use PHPUnit\Framework\TestCase;

/**
 * JSON-объект с ключами 0..n не становится списком.
 */
final class JsonDocumentsTest extends TestCase
{
    /**
     * Таблица costs остаётся объектом.
     *
     * @return void
     */
    public function testObjectNumericKeysStayJsonObject(): void
    {
        $decoded = JsonDocuments::decode('{"0":0,"1":2,"2":4}');

        self::assertInstanceOf(JsonObject::class, $decoded);
        self::assertSame(0, $decoded[0]);
        self::assertSame(2, $decoded[1]);
        self::assertSame(
            '{"0":0,"1":2,"2":4}',
            json_encode($decoded, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * JSON-массив остаётся списком.
     *
     * @return void
     */
    public function testJsonArrayStaysList(): void
    {
        $decoded = JsonDocuments::decode('[1,2]');

        self::assertIsArray($decoded);
        self::assertTrue(array_is_list($decoded));
        self::assertSame([1, 2], $decoded);
    }

    /**
     * HTTP array-параметр получает вложенные объекты как массивы.
     *
     * @return void
     */
    public function testUnwrapNestedObjectsToArrays(): void
    {
        $decoded = JsonDocuments::decode('{"scope":"all","userIds":[],"nested":{"x":{"base":1,"size":0}}}');
        self::assertInstanceOf(JsonObject::class, $decoded);
        self::assertSame(
            ['scope' => 'all', 'userIds' => [], 'nested' => ['x' => ['base' => 1, 'size' => 0]]],
            $decoded->toNestedArray(),
        );
    }
}
