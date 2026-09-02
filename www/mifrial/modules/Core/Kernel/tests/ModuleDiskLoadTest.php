<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\ModuleManager;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use PHPUnit\Framework\TestCase;

final class ModuleDiskLoadTest extends TestCase
{
    /**
     * На диске все группы, не только Core по имени вызова.
     *
     * @return void
     */
    public function testLoadAllFromDiskIncludesCoreModules(): void
    {
        $moduleManager = new ModuleManager(dirname(__DIR__, 4) . '/modules');
        $moduleManager->loadAllFromDisk();
        $keys = [];
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            $keys[] = $loadedModule['group'] . '/' . $loadedModule['name'];
        }

        self::assertContains('Core/Auth', $keys);
        self::assertContains('Core/Kernel', $keys);
        self::assertContains('Core/SmartTable', $keys);
        self::assertContains('Core/User', $keys);
    }

    /**
     * bootSetup грузит User и SmartTable eager, без необходимости lazy get.
     *
     * @return void
     */
    public function testBootSetupBindsDiskModules(): void
    {
        $application = (new ApplicationFactory())->bootSetup(dirname(__DIR__, 4));
        $keys = [];
        foreach ($application->getModuleManager()->getLoadedModules() as $loadedModule) {
            $keys[] = $loadedModule['group'] . '/' . $loadedModule['name'];
        }

        self::assertContains('Core/User', $keys);
        self::assertTrue($application->getLocator()->has(IUserContainer::class));
        self::assertTrue($application->getLocator()->has(ISmartTableContainer::class));
    }
}
