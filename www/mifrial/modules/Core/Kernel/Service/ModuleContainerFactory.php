<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Closure;
use Mifrial\Core\Kernel\Container\KernelContainer;
use Mifrial\Core\Kernel\Container\ModuleContainer;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Создание контейнера модуля по конфигурации.
 */
final class ModuleContainerFactory
{
    /**
     * Создаёт фабрику контейнеров.
     *
     * @param array<string, Closure> $kernelPortFactories Доп. порты только для Kernel.
     *
     * @return void
     */
    public function __construct(
        private readonly array $kernelPortFactories = [],
    ) {
    }

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
        if ($this->isKernelContainerClass($containerClass)) {
            $portFactories = array_merge($portFactories, $this->kernelPortFactories);
        }

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

    /**
     * Проверяет, что создаётся контейнер Kernel.
     *
     * @param mixed $containerClass Класс контейнера.
     *
     * @return bool true, если extra-порты Kernel нужно влить.
     */
    private function isKernelContainerClass(mixed $containerClass): bool
    {
        if ($containerClass === IKernelContainer::class || $containerClass === KernelContainer::class) {
            return true;
        }

        return is_string($containerClass)
            && class_exists($containerClass)
            && is_subclass_of($containerClass, KernelContainer::class);
    }
}
