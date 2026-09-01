<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Http\ResponseEmitter;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Composition root: сборка приложения из конфига.
 */
final class ApplicationFactory
{
    /**
     * Загружает конфигурацию и собирает приложение.
     *
     * @param string $root Корень Mifrial.
     *
     * @return Application Собранное приложение.
     */
    public function boot(string $root): Application
    {
        $serviceLocator = new ServiceLocator();
        $moduleManager = new ModuleManager($root . '/modules');
        $containerBinder = new ModuleContainerBinder();
        $moduleManager->loadCore();
        $moduleManager->getRoutes();
        $containerBinder->bindEager($serviceLocator, $moduleManager);
        $this->registerLazyFromCatalog($root, $serviceLocator, $moduleManager, $containerBinder);
        $serviceLocator->freeze();

        return new Application(
            $serviceLocator,
            $moduleManager,
            new Dispatcher($moduleManager),
            new ResponseEmitter(),
            new ErrorLogLogger(),
            $this->loadLocalConfig($root),
        );
    }

    /**
     * Читает config/local.php.
     *
     * @param string $root Корень Mifrial.
     *
     * @return array<string, mixed> Локальная конфигурация.
     */
    private function loadLocalConfig(string $root): array
    {
        $configPath = $root . '/config/local.php';
        $config = is_file($configPath) ? require $configPath : [];
        if (!is_array($config)) {
            return ['debug' => false];
        }

        if (!array_key_exists('debug', $config)) {
            $config['debug'] = false;
        }

        return $config;
    }

    /**
     * Регистрирует ленивые слоты из config/modules.php.
     *
     * @param string $root Корень Mifrial.
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param ModuleContainerBinder $containerBinder Сборщик контейнеров.
     *
     * @return void
     */
    private function registerLazyFromCatalog(
        string $root,
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
        ModuleContainerBinder $containerBinder,
    ): void {
        $catalogPath = $root . '/config/modules.php';
        $catalog = is_file($catalogPath) ? require $catalogPath : [];
        $lazyModules = is_array($catalog) ? ($catalog['lazy'] ?? []) : [];
        if (!is_array($lazyModules)) {
            return;
        }

        foreach ($lazyModules as $locatorKey => $moduleReference) {
            if (!$this->isLazyReference($locatorKey, $moduleReference)) {
                continue;
            }

            $containerBinder->registerLazy(
                $serviceLocator,
                $moduleManager,
                $locatorKey,
                $moduleReference['group'],
                $moduleReference['name'],
            );
        }
    }

    /**
     * Проверяет запись ленивого модуля в каталоге.
     *
     * @param mixed $locatorKey Ключ локатора.
     * @param mixed $moduleReference Описание модуля.
     *
     * @return bool true, если запись можно зарегистрировать.
     */
    private function isLazyReference(mixed $locatorKey, mixed $moduleReference): bool
    {
        return is_string($locatorKey)
            && is_array($moduleReference)
            && is_string($moduleReference['group'] ?? null)
            && is_string($moduleReference['name'] ?? null);
    }
}
