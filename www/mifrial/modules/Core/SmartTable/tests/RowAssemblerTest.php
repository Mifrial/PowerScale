<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Service\Query\RowAssembler;
use Mifrial\Core\SmartTable\Tests\Fixture\SampleTable;
use PHPUnit\Framework\TestCase;

final class RowAssemblerTest extends TestCase
{
    /**
     * Проверяет отказ на неизвестном ключе и заданном id.
     *
     * @return void
     */
    public function testAssembleInsertRejectsUnknownKeyAndId(): void
    {
        $assembler = new RowAssembler();
        $table = new SampleTable();

        try {
            $assembler->assembleInsert(['missing' => 'x'], $table);
            self::fail('unknown key must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $assembler->assembleInsert(['id' => 5, 'title' => 'a'], $table);
            self::fail('explicit id must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Проверяет отказ пустого update и ключа id.
     *
     * @return void
     */
    public function testAssembleUpdateRejectsEmptyAndId(): void
    {
        $assembler = new RowAssembler();
        $table = new SampleTable();

        try {
            $assembler->assembleUpdate([], $table);
            self::fail('empty update must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $assembler->assembleUpdate(['id' => 1], $table);
            self::fail('id on update must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Неизвестное имя в select не гидратируется.
     *
     * @return void
     */
    public function testHydrateSelectedRejectsUnknownField(): void
    {
        $assembler = new RowAssembler();
        try {
            $assembler->hydrateSelected(['id' => 1], new SampleTable(), ['missing']);
            self::fail('unknown select field must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}
