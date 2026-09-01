<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Closure;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Контейнер портов модуля с ленивыми фабриками.
 */
class ModuleContainer implements IModuleContainer
{
    /**
     * @var array<string, object>
     */
    private array $resolvedPorts = [];

    /**
     * @var array<string, true>
     */
    private array $resolvingPorts = [];

    private bool $frozen = false;

    /**
     * Создаёт контейнер модуля с картой портов.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров соседей.
     * @param array<string, Closure> $portFactories Карта порт → фабрика.
     *
     * @return void
     */
    public function __construct(
        private readonly IServiceLocator $serviceLocator,
        private array $portFactories,
    ) {
    }

    /**
     * Возвращает порт модуля и запоминает экземпляр.
     *
     * @param string $port Интерфейс или класс порта.
     *
     * @return object Реализация порта.
     *
     * @throws KernelException Если порт не объявлен, цикл или фабрика вернула не объект.
     */
    public function get(string $port): object
    {
        if (isset($this->resolvedPorts[$port])) {
            return $this->resolvedPorts[$port];
        }

        if (!isset($this->portFactories[$port])) {
            throw new KernelException('UNKNOWN_PORT', 'Unknown module port: ' . $port);
        }

        if (isset($this->resolvingPorts[$port])) {
            throw new KernelException('CIRCULAR_PORT', 'Circular module port resolution: ' . $port);
        }

        $this->resolvingPorts[$port] = true;
        try {
            $implementation = ($this->portFactories[$port])($this->serviceLocator, $this);
            if (!is_object($implementation)) {
                throw new KernelException('INVALID_PORT', 'Module port factory must return an object: ' . $port);
            }

            $this->resolvedPorts[$port] = $implementation;

            return $implementation;
        } finally {
            unset($this->resolvingPorts[$port]);
        }
    }

    /**
     * Подменяет порт, если контейнер ещё не заморожен и порт не разрешён.
     *
     * @param string $port Интерфейс или класс порта.
     * @param object $implementation Подмена реализации.
     *
     * @return void
     *
     * @throws KernelException Если контейнер заморожен или порт уже разрешён.
     */
    public function override(string $port, object $implementation): void
    {
        if ($this->frozen) {
            throw new KernelException('CONTAINER_FROZEN', 'Cannot override a frozen module container');
        }

        if (isset($this->resolvedPorts[$port])) {
            throw new KernelException('PORT_RESOLVED', 'Cannot override an already resolved port: ' . $port);
        }

        $this->resolvedPorts[$port] = $implementation;
    }

    /**
     * Запрещает дальнейшие подмены портов.
     *
     * @return void
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }
}
