<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Versioning\Space\Interface\Container\ISpaceContainer;
use Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog;
use PHPUnit\Framework\TestCase;

final class SpacePortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт каталог.
     *
     * @return void
     */
    public function testBootResolvesCatalog(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $catalog = $application->getLocator()->get(ISpaceContainer::class)->get(IVersionedCatalog::class);
        self::assertInstanceOf(IVersionedCatalog::class, $catalog);
    }
}
