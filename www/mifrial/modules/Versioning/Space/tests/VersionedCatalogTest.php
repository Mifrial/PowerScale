<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests;

use Mifrial\Core\Cache\Service\UnusableCacheStore;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use Mifrial\Versioning\Space\Dto\ClusterSpec;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use Mifrial\Versioning\Space\Service\VersionedCatalog;
use Mifrial\Versioning\Space\Tests\Fixture\NoteIdentityTable;
use PHPUnit\Framework\TestCase;
use stdClass;

final class VersionedCatalogTest extends TestCase
{
    /**
     * Нет класса в реестре → SPACE_INVALID.
     *
     * @return void
     */
    public function testUnknownIdentityIsInvalid(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $connection = $application->getLocator()->get(ISmartTableContainer::class)->get(IDatabaseConnection::class);
        if (!$connection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $catalog = new VersionedCatalog(
            GatewayHarness::make($connection),
            new UnusableCacheStore(),
            false,
            [],
        );
        try {
            $catalog->open(NoteIdentityTable::class);
            self::fail('unknown identity must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Identity не карта ST → SPACE_INVALID.
     *
     * @return void
     */
    public function testOpenClusterRejectsNonTable(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $connection = $application->getLocator()->get(ISmartTableContainer::class)->get(IDatabaseConnection::class);
        if (!$connection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $catalog = new VersionedCatalog(
            GatewayHarness::make($connection),
            new UnusableCacheStore(),
            false,
            [],
        );
        try {
            $catalog->openCluster(new ClusterSpec(
                stdClass::class,
                stdClass::class,
                stdClass::class,
                stdClass::class,
                stdClass::class,
            ));
            self::fail('non-table identity must fail');
        } catch (SpaceInvalidException $exception) {
            self::assertSame('SPACE_INVALID', $exception->getErrorCode());
        }
    }
}
