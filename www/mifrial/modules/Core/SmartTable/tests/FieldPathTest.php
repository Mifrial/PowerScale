<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Dto\FieldPath;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use PHPUnit\Framework\TestCase;

final class FieldPathTest extends TestCase
{
    /**
     * Разбор и отказы формы пути.
     *
     * @return void
     */
    public function testParseAndRejects(): void
    {
        $fieldPath = FieldPath::parse('parent_id.active');
        self::assertSame(['parent_id', 'active'], $fieldPath->segments());
        self::assertSame('parent_id.active', $fieldPath->key());
        self::assertNull(FieldPath::tryParse('parent_id'));
        foreach (['.', 'a.', '.a', 'a..b', 'a.B', 'a.b.c.'] as $invalidPath) {
            try {
                FieldPath::parse($invalidPath);
                self::fail('path must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }
}
