<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Closure;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Каталог контейнеров модулей и ленивых слотов.
 */
final class ServiceLocator implements IServiceLocator
{
    /**
     * @var array<string, object>
     */
    private array $entries = [];

    /**
     * @var array<string, true>
     */
    private array $resolvingKeys = [];

    private bool $frozen = false;

    /**
     * Регистрирует контейнер модуля или фабрику контейнера.
     *
     * @param string $locatorKey Интерфейс контейнера или алиас.
     * @param object $entry Контейнер или Closure-фабрика контейнера.
     *
     * @return void
     *
     * @throws KernelException Если локатор заморожен.
     */
    public function set(string $locatorKey, object $entry): void
    {
        if ($this->frozen && $this->resolvingKeys === []) {
            throw new KernelException('LOCATOR_FROZEN', 'Cannot register a container on a frozen locator');
        }

        $this->entries[$locatorKey] = $entry;
    }

    /**
     * Возвращает контейнер модуля и разрешает ленивую фабрику.
     *
     * @param string $locatorKey Интерфейс контейнера или алиас.
     *
     * @return IModuleContainer Контейнер модуля.
     *
     * @throws KernelException Если ключ неизвестен, цикл или фабрика вернула не контейнер.
     */
    public function get(string $locatorKey): IModuleContainer
    {
        if (!isset($this->entries[$locatorKey])) {
            throw new KernelException('UNKNOWN_CONTAINER', 'Unknown module container: ' . $locatorKey);
        }

        $entry = $this->entries[$locatorKey];
        if (!$entry instanceof Closure) {
            return $this->asModuleContainer($entry, $locatorKey);
        }

        if (isset($this->resolvingKeys[$locatorKey])) {
            throw new KernelException('CIRCULAR_CONTAINER', 'Circular module container resolution: ' . $locatorKey);
        }

        $this->resolvingKeys[$locatorKey] = true;
        try {
            $resolved = $entry($this);
            $moduleContainer = $this->asModuleContainer($resolved, $locatorKey);
            $this->entries[$locatorKey] = $moduleContainer;

            return $moduleContainer;
        } finally {
            unset($this->resolvingKeys[$locatorKey]);
        }
    }

    /**
     * Проверяет наличие контейнера в каталоге.
     *
     * @param string $locatorKey Интерфейс контейнера или алиас.
     *
     * @return bool true, если контейнер зарегистрирован.
     */
    public function has(string $locatorKey): bool
    {
        return isset($this->entries[$locatorKey]);
    }

    /**
     * Запрещает регистрацию новых контейнеров.
     *
     * @return void
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    /**
     * Проверяет, что значение является контейнером модуля.
     *
     * @param mixed $entry Результат фабрики или запись каталога.
     * @param string $locatorKey Ключ локатора.
     *
     * @return IModuleContainer Контейнер модуля.
     *
     * @throws KernelException Если значение не контейнер.
     */
    private function asModuleContainer(mixed $entry, string $locatorKey): IModuleContainer
    {
        if (!$entry instanceof IModuleContainer) {
            throw new KernelException(
                'INVALID_CONTAINER',
                'Locator factory must return IModuleContainer: ' . $locatorKey,
            );
        }

        return $entry;
    }
}
