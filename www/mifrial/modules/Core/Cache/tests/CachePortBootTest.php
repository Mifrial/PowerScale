<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Tests;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use PHPUnit\Framework\TestCase;

final class CachePortBootTest extends TestCase
{
    /**
     * boot резолвит ICacheStore без throw на штатном test.php.
     *
     * @return void
     */
    public function testBootResolvesStoreWithoutThrow(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $store = $application->getLocator()->get(ICacheContainer::class)->get(ICacheStore::class);
        self::assertInstanceOf(ICacheStore::class, $store);
    }
}
