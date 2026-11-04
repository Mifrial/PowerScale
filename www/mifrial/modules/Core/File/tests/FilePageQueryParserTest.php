<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\Action\FindFilePageInput;
use Mifrial\Core\File\Exception\FileInvalidException;
use Mifrial\Core\File\Service\FilePageQueryParser;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use PHPUnit\Framework\TestCase;

final class FilePageQueryParserTest extends TestCase
{
    /**
     * Полный фильтр разворачивается в запрос.
     *
     * @return void
     */
    public function testParsesFilters(): void
    {
        $query = (new FilePageQueryParser())->parse(new FindFilePageInput(
            25,
            50,
            OptionalString::present('image/png'),
            OptionalInt::present(7),
            OptionalBool::present(true),
            100,
            200,
        ));
        self::assertSame(25, $query->getLimit());
        self::assertSame(50, $query->getOffset());
        self::assertSame('image/png', $query->getMime());
        self::assertSame(7, $query->getOwnerId());
        self::assertTrue($query->getIsArchived());
        self::assertSame(100, $query->getFromUnix());
        self::assertSame(200, $query->getToUnix());
    }

    /**
     * Пустые optional дают null-фильтры.
     *
     * @return void
     */
    public function testEmptyFilters(): void
    {
        $query = (new FilePageQueryParser())->parse(new FindFilePageInput(
            1,
            0,
            OptionalString::absent(),
            OptionalInt::absent(),
            OptionalBool::absent(),
        ));
        self::assertNull($query->getMime());
        self::assertNull($query->getOwnerId());
        self::assertNull($query->getIsArchived());
        self::assertNull($query->getFromUnix());
        self::assertNull($query->getToUnix());
    }

    /**
     * Недопустимые границы и фильтры.
     *
     * @return void
     */
    public function testInvalidInput(): void
    {
        $parser = new FilePageQueryParser();
        foreach (
            [
                [0, 0, OptionalString::absent(), OptionalInt::absent(), OptionalBool::absent(), null, null],
                [101, 0, OptionalString::absent(), OptionalInt::absent(), OptionalBool::absent(), null, null],
                [1, -1, OptionalString::absent(), OptionalInt::absent(), OptionalBool::absent(), null, null],
                [1, 0, OptionalString::absent(), OptionalInt::absent(), OptionalBool::absent(), 20, 10],
                [1, 0, OptionalString::present('png'), OptionalInt::absent(), OptionalBool::absent(), null, null],
                [1, 0, OptionalString::absent(), OptionalInt::present(0), OptionalBool::absent(), null, null],
            ] as [$limit, $offset, $mime, $ownerId, $isArchived, $from, $to]
        ) {
            try {
                $parser->parse(new FindFilePageInput($limit, $offset, $mime, $ownerId, $isArchived, $from, $to));
                self::fail('invalid page input must fail');
            } catch (FileInvalidException $exception) {
                self::assertSame('FILE_INVALID', $exception->getErrorCode());
            }
        }
    }
}
