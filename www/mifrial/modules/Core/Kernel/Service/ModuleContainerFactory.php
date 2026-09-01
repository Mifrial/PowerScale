<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Closure;
use Mifrial\Core\Kernel\Interface\Service\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Создание контейнера модуля по конфигурации.
 */
final class ModuleContainerFactory
{
    /**
     * Создаёт контейнер модуля по классу из конфигурации.
     *
     * @param mixed $containerClass Класс контейнера.
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param array<string, mixed> $moduleConfig Конфигурация модуля.
     *
     * @return IModuleContainer Контейнер модуля.
     */
    public function create(
        mixed $containerClass,
        IServiceLocator $serviceLocator,
        array $moduleConfig,
    ): IModuleContainer {
        $portFactories = $this->extractPortFactories($moduleConfig);

        if (
            is_string($containerClass)
            && class_exists($containerClass)
            && is_subclass_of($containerClass, ModuleContainer::class)
        ) {
            return new $containerClass($serviceLocator, $portFactories);
        }

        if ($containerClass === IKernelContainer::class || $containerClass === KernelContainer::class) {
            return new KernelContainer($serviceLocator, $portFactories);
        }

        return new ModuleContainer($serviceLocator, $portFactories);
    }

    /**
     * Читает карту фабрик портов из конфигурации модуля.
     *
     * @param array<string, mixed> $moduleConfig Конфигурация модуля.
     *
     * @return array<string, Closure> Карта порт → фабрика.
     */
    private function extractPortFactories(array $moduleConfig): array
    {
        $portFactories = $moduleConfig['ports'] ?? [];
        if (!is_array($portFactories)) {
            return [];
        }

        $extractedFactories = [];
        foreach ($portFactories as $port => $portFactory) {
            if (is_string($port) && $portFactory instanceof Closure) {
                $extractedFactories[$port] = $portFactory;
            }
        }

        return $extractedFactories;
    }
}
