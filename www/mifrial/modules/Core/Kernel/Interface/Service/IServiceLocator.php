<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;

/**
 * Контракт каталога контейнеров модулей.
 */
interface IServiceLocator
{
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
    public function set(string $locatorKey, object $entry): void;

    /**
     * Возвращает контейнер модуля и разрешает ленивую фабрику.
     *
     * @param string $locatorKey Интерфейс контейнера или алиас.
     *
     * @return IModuleContainer Контейнер модуля.
     *
     * @throws KernelException Если ключ неизвестен, цикл или фабрика вернула не контейнер.
     */
    public function get(string $locatorKey): IModuleContainer;

    /**
     * Проверяет наличие контейнера в каталоге.
     *
     * @param string $locatorKey Интерфейс контейнера или алиас.
     *
     * @return bool true, если контейнер зарегистрирован.
     */
    public function has(string $locatorKey): bool;

    /**
     * Запрещает регистрацию новых контейнеров.
     *
     * @return void
     */
    public function freeze(): void;
}
