<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Mifrial\Core\User\Exception\UserInvalidException;
use Mifrial\Core\User\Service\YmdDateParser;
use PHPUnit\Framework\TestCase;

final class YmdDateParserTest extends TestCase
{
    /**
     * Пусто, валидный день, мусор.
     *
     * @return void
     */
    public function testParsesNullableCalendarDay(): void
    {
        $parser = new YmdDateParser();
        self::assertNull($parser->parseNullable(null));
        self::assertNull($parser->parseNullable(''));
        $parsedDay = $parser->parseNullable('2026-12-01');
        self::assertNotNull($parsedDay);
        self::assertSame(
            (new DateTimeImmutable('2026-12-01', new DateTimeZone('UTC')))->getTimestamp(),
            $parsedDay->toUnix(),
        );
        try {
            $parser->parseNullable('2026-13-01');
            self::fail('bad day');
        } catch (UserInvalidException $exception) {
            self::assertSame('USER_INVALID', $exception->getErrorCode());
        }
    }
}
