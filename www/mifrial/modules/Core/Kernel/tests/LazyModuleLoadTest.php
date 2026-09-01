<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Service\ModuleContainerBinder;
use Mifrial\Core\Kernel\Service\ModuleManager;
use Mifrial\Core\Kernel\Service\ServiceLocator;
use Mifrial\Core\Kernel\Tests\Fixture\ILazyStubContainer;
use PHPUnit\Framework\TestCase;

final class LazyModuleLoadTest extends TestCase
{
    /**
     * Проверяет, что ленивый модуль загружается только при первом get контейнера.
     *
     * @return void
     */
    public function testLazyContainerLoadsOnFirstGet(): void
    {
        $modulesRoot = __DIR__ . '/fixtures/modules';
        $serviceLocator = new ServiceLocator();
        $moduleManager = new ModuleManager($modulesRoot);
        $containerBinder = new ModuleContainerBinder();
        $containerBinder->registerLazy(
            $serviceLocator,
            $moduleManager,
            ILazyStubContainer::class,
            'Demo',
            'LazyStub',
        );

        self::assertSame([], $moduleManager->getLoadedModules());
        self::assertTrue($serviceLocator->has(ILazyStubContainer::class));

        $moduleContainer = $serviceLocator->get(ILazyStubContainer::class);
        $loadedModules = $moduleManager->getLoadedModules();

        self::assertInstanceOf(ILazyStubContainer::class, $moduleContainer);
        self::assertCount(1, $loadedModules);
        self::assertSame('Demo', $loadedModules[0]['group']);
        self::assertSame('LazyStub', $loadedModules[0]['name']);
        self::assertSame($moduleContainer, $serviceLocator->get(ILazyStubContainer::class));
    }

    /**
     * Проверяет регистрацию locator из module.config после lazy get.
     *
     * @return void
     */
    public function testLazyGetRegistersModuleLocatorKey(): void
    {
        $modulesRoot = __DIR__ . '/fixtures/modules';
        $serviceLocator = new ServiceLocator();
        $moduleManager = new ModuleManager($modulesRoot);
        $containerBinder = new ModuleContainerBinder();
        $containerBinder->registerLazy(
            $serviceLocator,
            $moduleManager,
            'LazyAlias',
            'Demo',
            'LazyStub',
        );
        $serviceLocator->freeze();

        $moduleContainer = $serviceLocator->get('LazyAlias');

        self::assertTrue($serviceLocator->has(ILazyStubContainer::class));
        self::assertSame($moduleContainer, $serviceLocator->get(ILazyStubContainer::class));
    }
}
