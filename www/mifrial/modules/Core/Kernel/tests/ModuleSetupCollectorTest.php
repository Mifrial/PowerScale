<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Agent\Setup\AgentModuleSetup;
use Mifrial\Core\Auth\Setup\AuthModuleSetup;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\Setup\ModuleSetupCollector;
use Mifrial\Core\Kernel\Setup\KernelModuleSetup;
use Mifrial\Core\Mail\Setup\MailModuleSetup;
use Mifrial\Core\SmartTable\Setup\SmartTableModuleSetup;
use Mifrial\Core\User\Setup\UserModuleSetup;
use PHPUnit\Framework\TestCase;

final class ModuleSetupCollectorTest extends TestCase
{
    /**
     * Ключ setup есть у Agent, Auth, Mail, Kernel, SmartTable и User.
     *
     * @return void
     */
    public function testCollectsDeclaredSetupsOnly(): void
    {
        $application = (new ApplicationFactory())->bootSetup(dirname(__DIR__, 4));
        $entries = (new ModuleSetupCollector())->collect(
            $application->getModuleManager(),
            $application->getLocator(),
        );
        $byKey = [];
        foreach ($entries as $entry) {
            $byKey[$entry['key']] = $entry['setup'];
        }

        self::assertInstanceOf(AgentModuleSetup::class, $byKey['Core/Agent']);
        self::assertInstanceOf(AuthModuleSetup::class, $byKey['Core/Auth']);
        self::assertInstanceOf(MailModuleSetup::class, $byKey['Core/Mail']);
        self::assertInstanceOf(KernelModuleSetup::class, $byKey['Core/Kernel']);
        self::assertInstanceOf(SmartTableModuleSetup::class, $byKey['Core/SmartTable']);
        self::assertInstanceOf(UserModuleSetup::class, $byKey['Core/User']);
    }
}
