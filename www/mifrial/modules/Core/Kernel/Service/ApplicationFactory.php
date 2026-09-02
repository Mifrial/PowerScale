<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Http\RequestContext;
use Mifrial\Core\Kernel\Http\ResponseEmitter;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
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
        return $this->assemble($root, false);
    }

    /**
     * Собирает приложение со всеми модулями на диске для CLI setup.
     *
     * Без ленивых слотов catalog: freeze после eager всех каталогов.
     *
     * @param string $root Корень Mifrial.
     *
     * @return Application Собранное приложение.
     */
    public function bootSetup(string $root): Application
    {
        return $this->assemble($root, true);
    }

    /**
     * Собирает локатор и менеджер модулей.
     *
     * @param string $root Корень Mifrial.
     * @param bool $loadAllDiskModules true — все группы на диске, без lazy catalog.
     *
     * @return Application Собранное приложение.
     */
    private function assemble(string $root, bool $loadAllDiskModules): Application
    {
        $config = (new LocalConfigLoader())->load($root);
        $serviceLocator = new ServiceLocator();
        $moduleManager = new ModuleManager($root . '/modules');
        $requestContext = new RequestContext();
        $containerBinder = $this->createContainerBinder($config, $requestContext);
        if ($loadAllDiskModules) {
            $moduleManager->loadAllFromDisk();
        } else {
            $moduleManager->loadCore();
        }

        $moduleManager->getRoutes();
        $containerBinder->bindEager($serviceLocator, $moduleManager);
        if (!$loadAllDiskModules) {
            $this->registerLazyFromCatalog($root, $serviceLocator, $moduleManager, $containerBinder);
        }

        $serviceLocator->freeze();

        return new Application(
            $serviceLocator,
            $moduleManager,
            new Dispatcher($moduleManager),
            new ResponseEmitter(requestContext: $requestContext),
            new ErrorLogLogger(),
            $config,
        );
    }

    /**
     * Собирает binder с extra-портами только для Kernel.
     *
     * @param array<string, mixed> $config Локальная конфигурация.
     * @param IRequestContext $requestContext Контекст cookie процесса.
     *
     * @return ModuleContainerBinder Сборщик контейнеров.
     */
    private function createContainerBinder(array $config, IRequestContext $requestContext): ModuleContainerBinder
    {
        $runtimeConfig = RuntimeConfig::fromLocal($config);
        $kernelPortFactories = [
            IRuntimeConfig::class => static fn (): IRuntimeConfig => $runtimeConfig,
            IRequestContext::class => static fn (): IRequestContext => $requestContext,
        ];

        return new ModuleContainerBinder(new ModuleContainerFactory($kernelPortFactories));
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
