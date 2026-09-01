<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Exception\ModuleManager\InvalidModuleConfigException;
use Mifrial\Core\Kernel\Exception\ModuleManager\ModuleManagerException;

/**
 * Контракт загрузки модулей и маршрутов.
 */
interface IModuleManager
{
    /**
     * Подключает модуль, если его конфигурация существует.
     *
     * @param string $group Группа модуля.
     * @param string $name Имя модуля.
     *
     * @return bool Признак успешного подключения.
     *
     * @throws InvalidModuleConfigException Если конфигурация некорректна.
     */
    public function includeModule(string $group, string $name): bool;

    /**
     * Подключает обязательный модуль или выбрасывает исключение.
     *
     * @param string $group Группа модуля.
     * @param string $name Имя модуля.
     *
     * @return void
     *
     * @throws ModuleManagerException Если модуль не найден или конфигурация некорректна.
     */
    public function requireModule(string $group, string $name): void;

    /**
     * Возвращает маршруты подключённых модулей.
     *
     * @return array<string, array{handler: string, group: string, name: string, csrf?: bool}> Карта маршрутов.
     *
     * @throws InvalidModuleConfigException Если маршруты некорректны или дублируются.
     */
    public function getRoutes(): array;

    /**
     * Подключает все модули группы Core.
     *
     * @return void
     *
     * @throws KernelException Если каталог Core недоступен.
     * @throws ModuleManagerException Если модуль Core не найден или конфигурация некорректна.
     */
    public function loadCore(): void;

    /**
     * Возвращает загруженные модули.
     *
     * @return array<int, array{group: string, name: string, config: array<string, mixed>}> Список модулей.
     */
    public function getLoadedModules(): array;

    /**
     * Привязывает контейнер к загруженному модулю.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     * @param IModuleContainer $moduleContainer Контейнер модуля.
     *
     * @return void
     *
     * @throws KernelException Если модуль не загружен.
     */
    public function bindContainer(
        string $moduleGroup,
        string $moduleName,
        IModuleContainer $moduleContainer,
    ): void;

    /**
     * Проверяет, привязан ли контейнер модуля.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return bool true, если контейнер уже привязан.
     */
    public function hasContainer(string $moduleGroup, string $moduleName): bool;

    /**
     * Возвращает контейнер модуля для composition root и Kernel.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return IModuleContainer Контейнер модуля.
     *
     * @throws KernelException Если контейнер не привязан.
     */
    public function getContainer(string $moduleGroup, string $moduleName): IModuleContainer;
}
