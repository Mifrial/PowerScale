<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Closure;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Сборка и регистрация контейнеров модулей.
 */
final class ModuleContainerBinder
{
    /**
     * Создаёт сборщик контейнеров.
     *
     * @param ModuleContainerFactory $containerFactory Фабрика контейнеров.
     *
     * @return void
     */
    public function __construct(
        private readonly ModuleContainerFactory $containerFactory = new ModuleContainerFactory(),
    ) {
    }

    /**
     * Собирает контейнеры уже загруженных модулей и регистрирует их в локаторе.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     *
     * @return void
     */
    public function bindEager(
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
    ): void {
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            $moduleContainer = $this->attachLoadedModule(
                $serviceLocator,
                $moduleManager,
                $loadedModule['group'],
                $loadedModule['name'],
            );
            $this->registerContainerInLocator(
                $serviceLocator,
                $loadedModule['config'],
                $moduleContainer,
            );
        }
    }

    /**
     * Регистрирует ленивый слот контейнера без загрузки модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param string $locatorKey Интерфейс контейнера.
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return void
     */
    public function registerLazy(
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
        string $locatorKey,
        string $moduleGroup,
        string $moduleName,
    ): void {
        $serviceLocator->set(
            $locatorKey,
            $this->lazyContainerFactory($moduleManager, $moduleGroup, $moduleName),
        );
    }

    /**
     * Создаёт контейнер загруженного модуля, если он ещё не привязан.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return IModuleContainer Контейнер модуля.
     *
     * @throws KernelException Если модуль не загружен.
     */
    public function attachLoadedModule(
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
        string $moduleGroup,
        string $moduleName,
    ): IModuleContainer {
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            if (
                $loadedModule['group'] === $moduleGroup
                && $loadedModule['name'] === $moduleName
            ) {
                return $this->bindIfNeeded(
                    $serviceLocator,
                    $moduleManager,
                    $loadedModule,
                );
            }
        }

        throw new KernelException(
            'MODULE_NOT_LOADED',
            'Cannot attach container for unloaded module: ' . $moduleGroup . '/' . $moduleName,
        );
    }

    /**
     * Регистрирует ключ locator из конфига загруженного модуля.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     * @param IModuleContainer $moduleContainer Контейнер модуля.
     *
     * @return void
     */
    public function registerLocatorFromLoadedModule(
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
        string $moduleGroup,
        string $moduleName,
        IModuleContainer $moduleContainer,
    ): void {
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            if (
                $loadedModule['group'] === $moduleGroup
                && $loadedModule['name'] === $moduleName
            ) {
                $this->registerContainerInLocator(
                    $serviceLocator,
                    $loadedModule['config'],
                    $moduleContainer,
                );

                return;
            }
        }
    }

    /**
     * Привязывает контейнер, если его ещё нет.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param array{group: string, name: string, config: array<string, mixed>} $loadedModule Загруженный модуль.
     *
     * @return IModuleContainer Контейнер модуля.
     */
    private function bindIfNeeded(
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
        array $loadedModule,
    ): IModuleContainer {
        if ($moduleManager->hasContainer($loadedModule['group'], $loadedModule['name'])) {
            return $moduleManager->getContainer($loadedModule['group'], $loadedModule['name']);
        }

        $moduleContainer = $this->containerFactory->create(
            $loadedModule['config']['container'] ?? ModuleContainer::class,
            $serviceLocator,
            $loadedModule['config'],
        );
        $moduleManager->bindContainer(
            $loadedModule['group'],
            $loadedModule['name'],
            $moduleContainer,
        );
        $moduleContainer->freeze();

        return $moduleContainer;
    }

    /**
     * Регистрирует контейнер в локаторе, если модуль объявил ключ.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param array<string, mixed> $moduleConfig Конфигурация модуля.
     * @param IModuleContainer $moduleContainer Контейнер модуля.
     *
     * @return void
     */
    private function registerContainerInLocator(
        IServiceLocator $serviceLocator,
        array $moduleConfig,
        IModuleContainer $moduleContainer,
    ): void {
        $locatorKey = $moduleConfig['locator'] ?? null;
        if (is_string($locatorKey) && $locatorKey !== '') {
            $serviceLocator->set($locatorKey, $moduleContainer);
        }
    }

    /**
     * Собирает фабрику ленивого слота.
     *
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return Closure Фабрика контейнера.
     */
    private function lazyContainerFactory(
        IModuleManager $moduleManager,
        string $moduleGroup,
        string $moduleName,
    ): Closure {
        $binder = $this;

        return static function (IServiceLocator $resolvedLocator) use (
            $binder,
            $moduleManager,
            $moduleGroup,
            $moduleName,
        ): IModuleContainer {
            return $binder->resolveLazyContainer(
                $resolvedLocator,
                $moduleManager,
                $moduleGroup,
                $moduleName,
            );
        };
    }

    /**
     * Загружает модуль и регистрирует контейнер для ленивого слота.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param IModuleManager $moduleManager Менеджер модулей.
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return IModuleContainer Контейнер модуля.
     */
    private function resolveLazyContainer(
        IServiceLocator $serviceLocator,
        IModuleManager $moduleManager,
        string $moduleGroup,
        string $moduleName,
    ): IModuleContainer {
        $moduleManager->requireModule($moduleGroup, $moduleName);
        $moduleContainer = $this->attachLoadedModule(
            $serviceLocator,
            $moduleManager,
            $moduleGroup,
            $moduleName,
        );
        $this->registerLocatorFromLoadedModule(
            $serviceLocator,
            $moduleManager,
            $moduleGroup,
            $moduleName,
            $moduleContainer,
        );

        return $moduleContainer;
    }
}
