<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\FieldSettings;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\DateTimeField;
use Mifrial\Core\SmartTable\Tests\Fixture\NowCreatedTable;
use Mifrial\Core\SmartTable\Tests\Fixture\NowOnStringTable;
use Mifrial\Core\SmartTable\Value\DateTimeNow;
use PHPUnit\Framework\TestCase;

final class DateTimeNowTest extends TestCase
{
    /**
     * Два cast без ключа — разные экземпляры; явный момент сохраняется.
     *
     * @return void
     */
    public function testCastNowIsFreshAndExplicitWins(): void
    {
        $dateField = new DateTimeField(
            'created',
            FieldSettings::fromOptions(['required' => true, 'default' => DateTimeNow::instance()]),
        );
        $first = $dateField->cast(null, false);
        $second = $dateField->cast(null, false);
        self::assertInstanceOf(UnixDateTime::class, $first);
        self::assertInstanceOf(UnixDateTime::class, $second);
        self::assertNotSame($first, $second);
        $moment = UnixDateTime::fromUnix(1700000000);
        self::assertSame(1700000000, $dateField->cast($moment, true)->toUnix());
        try {
            $dateField->cast(null, true);
            self::fail('explicit null required must fail');
        } catch (FieldRequiredException $exception) {
            self::assertSame('FIELD_REQUIRED', $exception->getErrorCode());
        }
    }

    /**
     * Sentinel на string — MAP_INVALID; карта с now на datetime собирается.
     *
     * @return void
     */
    public function testMapRejectsNowOnString(): void
    {
        try {
            (new NowOnStringTable())->getMap();
            self::fail('now on string must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        self::assertArrayHasKey('created', (new NowCreatedTable())->getMap());
    }
}
