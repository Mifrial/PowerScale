<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Throwable;

/**
 * Ищет ILogger по class-string из site config среди портов загруженных модулей.
 */
final class ConfiguredLoggerResolver
{
    /**
     * Возвращает адаптер или null, если ключ непригоден.
     *
     * @param mixed $loggerPort Class-string порта.
     * @param IModuleManager $moduleManager Загруженные модули.
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ILogger|null Адаптер.
     */
    public function resolve(
        mixed $loggerPort,
        IModuleManager $moduleManager,
        IServiceLocator $serviceLocator,
    ): ?ILogger {
        if (!is_string($loggerPort) || $loggerPort === '') {
            return null;
        }

        $locatorKey = $this->uniqueLocatorKey($loggerPort, $moduleManager);
        if ($locatorKey === null) {
            return null;
        }

        return $this->adapterFromLocator($locatorKey, $loggerPort, $serviceLocator);
    }

    /**
     * Locator ровно одного модуля, объявившего порт.
     *
     * @param string $loggerPort Ключ ports.
     * @param IModuleManager $moduleManager Загруженные модули.
     *
     * @return string|null Ключ локатора.
     */
    private function uniqueLocatorKey(string $loggerPort, IModuleManager $moduleManager): ?string
    {
        $locatorKeys = [];
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            $locatorKey = $this->locatorKeyIfPortDeclared($loggerPort, $loadedModule['config']);
            if ($locatorKey !== null) {
                $locatorKeys[] = $locatorKey;
            }
        }

        if (count($locatorKeys) !== 1) {
            return null;
        }

        return $locatorKeys[0];
    }

    /**
     * Ключ locator, если порт есть в карте модуля.
     *
     * @param string $loggerPort Ключ ports.
     * @param array<string, mixed> $moduleConfig Конфиг модуля.
     *
     * @return string|null Ключ локатора.
     */
    private function locatorKeyIfPortDeclared(string $loggerPort, array $moduleConfig): ?string
    {
        $portFactories = $moduleConfig['ports'] ?? [];
        if (!is_array($portFactories) || !isset($portFactories[$loggerPort])) {
            return null;
        }

        $locatorKey = $moduleConfig['locator'] ?? null;
        if (!is_string($locatorKey) || $locatorKey === '') {
            return null;
        }

        return $locatorKey;
    }

    /**
     * Берёт порт с контейнера.
     *
     * @param string $locatorKey Ключ локатора.
     * @param string $loggerPort Ключ порта.
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ILogger|null Адаптер.
     */
    private function adapterFromLocator(
        string $locatorKey,
        string $loggerPort,
        IServiceLocator $serviceLocator,
    ): ?ILogger {
        try {
            $adapter = $serviceLocator->get($locatorKey)->get($loggerPort);
        } catch (Throwable) {
            return null;
        }

        return $adapter instanceof ILogger ? $adapter : null;
    }
}
