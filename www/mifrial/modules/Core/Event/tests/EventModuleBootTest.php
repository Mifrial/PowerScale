<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Tests;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Event\Service\EventManager;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет загрузку Event через Core container.
 */
final class EventModuleBootTest extends TestCase
{
    /**
     * Проверяет locator, container, memoization и module config.
     *
     * @return void
     */
    public function testEventModuleBootsThroughCoreContainer(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $locator = $application->getLocator();
        $moduleManager = $application->getModuleManager();

        self::assertTrue($locator->has(IEventContainer::class));
        self::assertTrue($moduleManager->hasContainer('Core', 'Event'));

        $eventContainer = $locator->get(IEventContainer::class);
        $firstManager = $eventContainer->get(IEventManager::class);
        $secondManager = $eventContainer->get(IEventManager::class);

        self::assertInstanceOf(EventManager::class, $firstManager);
        self::assertSame($firstManager, $secondManager);
        self::assertSame(
            [],
            $this->getEventModuleConfig($moduleManager)['events'] ?? null,
        );
        self::assertSame(
            [],
            $this->getEventModuleConfig($moduleManager)['routes'] ?? null,
        );
    }

    /**
     * Возвращает конфиг загруженного Core/Event.
     *
     * @param IModuleManager $moduleManager Менеджер модулей.
     *
     * @return array<string, mixed> Конфиг Event.
     */
    private function getEventModuleConfig(IModuleManager $moduleManager): array
    {
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            if (
                $loadedModule['group'] === 'Core'
                && $loadedModule['name'] === 'Event'
            ) {
                return $loadedModule['config'];
            }
        }

        self::fail('Core/Event module was not loaded');
    }
}
