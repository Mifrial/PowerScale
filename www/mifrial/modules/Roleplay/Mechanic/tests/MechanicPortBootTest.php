<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use PHPUnit\Framework\TestCase;

final class MechanicPortBootTest extends TestCase
{
    /**
     * Lazy-контейнер отдаёт фасад.
     *
     * @return void
     */
    public function testBootResolvesMechanics(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $mechanics = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanics::class);
        self::assertInstanceOf(IMechanics::class, $mechanics);
        self::assertFalse(method_exists(IMechanics::class, 'delete'));
        self::assertArrayHasKey(
            'mechanic.getList',
            $application->getModuleManager()->getRoutes(),
        );
        self::assertFalse(method_exists(IMechanics::class, 'getByCode'));
    }
}
