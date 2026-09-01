<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\SmartTable\Dto\ColumnMeta;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Service\ColumnSchema;
use Mifrial\Core\SmartTable\Service\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\Fixture\CustomColumnField;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TableSchemaColumnTest extends TestCase
{
    /**
     * Проверяет отказ неизвестного sqlType и VARCHAR без длины.
     *
     * @return void
     */
    public function testDefineColumnRejectsInvalidMeta(): void
    {
        $schema = new ColumnSchema(new IlluminateDatabaseConnection(
            new IlluminateConnectionFactory(),
            DatabaseSettings::fromConfig([]),
        ));
        $blueprint = new Blueprint('st_meta');
        $defineColumn = new ReflectionMethod(ColumnSchema::class, 'defineColumn');
        $defineColumn->setAccessible(true);

        try {
            $defineColumn->invoke($schema, $blueprint, new CustomColumnField('x', new ColumnMeta('BLOB')));
            self::fail('unknown sqlType must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            $defineColumn->invoke($schema, $blueprint, new CustomColumnField('y', new ColumnMeta('VARCHAR')));
            self::fail('varchar without length must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}
