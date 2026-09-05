<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use PHPUnit\Framework\TestCase;

final class LazyCatalogRoutesTest extends TestCase
{
    /**
     * boot() кладёт маршруты lazy-модуля в карту, контейнер не собирает.
     *
     * @return void
     */
    public function testBootRegistersLazyRoutesWithoutContainer(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $moduleManager = $application->getModuleManager();
        $routes = $moduleManager->getRoutes();

        self::assertArrayHasKey('chat.getChats', $routes);
        self::assertSame(true, $routes['chat.getChats']['csrf'] ?? null);
        self::assertTrue($this->isChatLoaded($moduleManager->getLoadedModules()));
        self::assertFalse($moduleManager->hasContainer('Messages', 'Chat'));
        self::assertTrue($application->getLocator()->has(IChatContainer::class));

        $ping = $application->dispatch('mifrial.ping', null)->toArray();
        self::assertTrue($ping['success']);
        self::assertFalse($moduleManager->hasContainer('Messages', 'Chat'));
    }

    /**
     * Модуль Messages/Chat уже в loaded.
     *
     * @param array<int, array{group: string, name: string, config: array<string, mixed>}> $loadedModules Список.
     *
     * @return bool true, если Chat загружен.
     */
    private function isChatLoaded(array $loadedModules): bool
    {
        foreach ($loadedModules as $loadedModule) {
            if ($loadedModule['group'] === 'Messages' && $loadedModule['name'] === 'Chat') {
                return true;
            }
        }

        return false;
    }
}
