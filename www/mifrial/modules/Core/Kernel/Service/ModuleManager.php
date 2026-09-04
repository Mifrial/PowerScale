<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Exception\ModuleManager\InvalidModuleConfigException;
use Mifrial\Core\Kernel\Exception\ModuleManager\ModuleNotFoundException;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;

/**
 * Загрузка module.config и привязка контейнеров.
 */
final class ModuleManager implements IModuleManager
{
    /**
     * @var array<string, array{
     *     group: string,
     *     name: string,
     *     config: array<string, mixed>,
     *     container: IModuleContainer|null
     * }>
     */
    private array $loadedModules = [];

    /**
     * Создаёт менеджер модулей.
     *
     * @param string $modulesRoot Корень каталогов модулей.
     * @param ModuleRouteCatalog $routeCatalog Каталог маршрутов модулей.
     *
     * @return void
     */
    public function __construct(
        private readonly string $modulesRoot,
        private readonly ModuleRouteCatalog $routeCatalog = new ModuleRouteCatalog(),
    ) {
    }

    /**
     * Подключает модуль, если его конфигурация существует.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return bool true, если модуль подключён или уже загружен.
     *
     * @throws InvalidModuleConfigException Если конфигурация некорректна.
     */
    public function includeModule(string $moduleGroup, string $moduleName): bool
    {
        $moduleKey = $moduleGroup . '/' . $moduleName;
        if (isset($this->loadedModules[$moduleKey])) {
            return true;
        }

        $moduleConfigPath = $this->modulesRoot . '/' . $moduleGroup . '/' . $moduleName . '/module.config.php';
        if (!is_file($moduleConfigPath)) {
            return false;
        }

        $moduleConfig = $this->loadModuleConfig($moduleConfigPath, $moduleGroup, $moduleName);
        $this->routeCatalog->assertNewModuleRoutes(
            $moduleGroup,
            $moduleName,
            $moduleConfig,
            array_keys($this->getRoutes()),
        );
        $this->assertRequestBind($moduleGroup, $moduleName, $moduleConfig);

        $this->loadedModules[$moduleKey] = [
            'group' => $moduleGroup,
            'name' => $moduleName,
            'config' => $moduleConfig,
            'container' => null,
        ];

        return true;
    }

    /**
     * Подключает обязательный модуль.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return void
     *
     * @throws ModuleNotFoundException Если модуль не найден.
     * @throws InvalidModuleConfigException Если конфигурация некорректна.
     */
    public function requireModule(string $moduleGroup, string $moduleName): void
    {
        if (!$this->includeModule($moduleGroup, $moduleName)) {
            throw new ModuleNotFoundException($moduleGroup, $moduleName);
        }
    }

    /**
     * Возвращает маршруты подключённых модулей.
     *
     * @return array<string, array{handler: string, group: string, name: string, csrf?: bool}> Карта маршрутов.
     *
     * @throws InvalidModuleConfigException Если маршруты некорректны или дублируются.
     */
    public function getRoutes(): array
    {
        return $this->routeCatalog->collect($this->loadedModules);
    }

    /**
     * Подключает все модули группы Core.
     *
     * @return void
     *
     * @throws KernelException Если каталог Core недоступен.
     * @throws ModuleNotFoundException Если модуль Core не найден.
     * @throws InvalidModuleConfigException Если конфигурация некорректна.
     */
    public function loadCore(): void
    {
        $coreModulesDirectory = $this->modulesRoot . '/Core';
        if (!is_dir($coreModulesDirectory)) {
            throw new KernelException(
                'CORE_MODULES_MISSING',
                'Core modules directory is missing: ' . $coreModulesDirectory,
            );
        }

        $coreModuleNames = scandir($coreModulesDirectory);
        if ($coreModuleNames === false) {
            throw new KernelException(
                'CORE_MODULES_UNREADABLE',
                'Cannot read Core modules directory: ' . $coreModulesDirectory,
            );
        }

        foreach ($coreModuleNames as $coreModuleName) {
            if ($coreModuleName === '.' || $coreModuleName === '..') {
                continue;
            }

            if (is_dir($coreModulesDirectory . '/' . $coreModuleName)) {
                $this->requireModule('Core', $coreModuleName);
            }
        }
    }

    /**
     * Подключает все модули на диске: группы и имена с module.config.php.
     *
     * @return void
     *
     * @throws KernelException Если корень модулей недоступен.
     * @throws ModuleNotFoundException Если require не нашёл файл после проверки.
     * @throws InvalidModuleConfigException Если конфигурация некорректна.
     */
    public function loadAllFromDisk(): void
    {
        foreach ($this->childDirectoryNames($this->modulesRoot) as $moduleGroup) {
            $groupDirectory = $this->modulesRoot . '/' . $moduleGroup;
            foreach ($this->childDirectoryNames($groupDirectory) as $moduleName) {
                if (is_file($groupDirectory . '/' . $moduleName . '/module.config.php')) {
                    $this->requireModule($moduleGroup, $moduleName);
                }
            }
        }
    }

    /**
     * Возвращает загруженные модули без контейнеров.
     *
     * @return array<int, array{group: string, name: string, config: array<string, mixed>}> Список модулей.
     */
    public function getLoadedModules(): array
    {
        $loadedModules = [];
        foreach ($this->loadedModules as $loadedModule) {
            $loadedModules[] = [
                'group' => $loadedModule['group'],
                'name' => $loadedModule['name'],
                'config' => $loadedModule['config'],
            ];
        }

        return $loadedModules;
    }

    /**
     * Привязывает контейнер к уже загруженному модулю.
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
    ): void {
        $moduleKey = $moduleGroup . '/' . $moduleName;
        if (!isset($this->loadedModules[$moduleKey])) {
            throw new KernelException(
                'MODULE_NOT_LOADED',
                'Cannot bind container for unloaded module: ' . $moduleKey,
            );
        }

        $this->loadedModules[$moduleKey]['container'] = $moduleContainer;
    }

    /**
     * Проверяет, привязан ли контейнер модуля.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return bool true, если контейнер уже привязан.
     */
    public function hasContainer(string $moduleGroup, string $moduleName): bool
    {
        $moduleKey = $moduleGroup . '/' . $moduleName;
        $moduleContainer = $this->loadedModules[$moduleKey]['container'] ?? null;

        return $moduleContainer instanceof IModuleContainer;
    }

    /**
     * Возвращает контейнер загруженного модуля.
     *
     * Метод предназначен для composition root и Kernel. Прикладные модули
     * получают контейнеры соседей через локатор по интерфейсу контейнера.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return IModuleContainer Контейнер модуля.
     *
     * @throws KernelException Если модуль не загружен или контейнер не привязан.
     */
    public function getContainer(string $moduleGroup, string $moduleName): IModuleContainer
    {
        $moduleKey = $moduleGroup . '/' . $moduleName;
        $moduleContainer = $this->loadedModules[$moduleKey]['container'] ?? null;
        if (!$moduleContainer instanceof IModuleContainer) {
            throw new KernelException('CONTAINER_UNBOUND', 'Module container is not bound: ' . $moduleKey);
        }

        return $moduleContainer;
    }

    /**
     * Загружает и проверяет конфигурацию модуля.
     *
     * @param string $moduleConfigPath Путь к конфигурации.
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     *
     * @return array<string, mixed> Конфигурация модуля.
     *
     * @throws InvalidModuleConfigException Если конфигурация не является массивом.
     */
    private function loadModuleConfig(string $moduleConfigPath, string $moduleGroup, string $moduleName): array
    {
        $moduleConfig = require $moduleConfigPath;
        if (!is_array($moduleConfig)) {
            throw new InvalidModuleConfigException($moduleGroup, $moduleName);
        }

        return $moduleConfig;
    }

    /**
     * Проверяет, что request_bind — порт из карты ports.
     *
     * @param string $moduleGroup Группа.
     * @param string $moduleName Имя.
     * @param array<string, mixed> $moduleConfig Конфиг.
     *
     * @return void
     *
     * @throws InvalidModuleConfigException Если ключ задан криво.
     */
    private function assertRequestBind(string $moduleGroup, string $moduleName, array $moduleConfig): void
    {
        if (!array_key_exists('request_bind', $moduleConfig)) {
            return;
        }

        $requestBind = $moduleConfig['request_bind'];
        $portFactories = $moduleConfig['ports'] ?? [];
        if (!is_string($requestBind) || $requestBind === '' || !is_array($portFactories) || !isset($portFactories[$requestBind])) {
            throw new InvalidModuleConfigException(
                $moduleGroup,
                $moduleName,
                'request_bind must be a ports key',
            );
        }
    }

    /**
     * Возвращает имена подкаталогов, отсортированные.
     *
     * @param string $directoryPath Каталог.
     *
     * @return array<int, string> Имена.
     *
     * @throws KernelException Если каталог нельзя прочитать.
     */
    private function childDirectoryNames(string $directoryPath): array
    {
        $entryNames = $this->scanDirectory($directoryPath);
        $directoryNames = [];
        foreach ($entryNames as $entryName) {
            if ($entryName === '.' || $entryName === '..') {
                continue;
            }

            if (is_dir($directoryPath . '/' . $entryName)) {
                $directoryNames[] = $entryName;
            }
        }

        sort($directoryNames);

        return $directoryNames;
    }

    /**
     * Читает имена записей каталога.
     *
     * @param string $directoryPath Каталог.
     *
     * @return array<int, string> Имена scandir.
     *
     * @throws KernelException Если каталог нельзя прочитать.
     */
    private function scanDirectory(string $directoryPath): array
    {
        if (!is_dir($directoryPath)) {
            throw new KernelException(
                'MODULES_MISSING',
                'Modules directory is missing: ' . $directoryPath,
            );
        }

        $entryNames = scandir($directoryPath);
        if ($entryNames === false) {
            throw new KernelException(
                'MODULES_UNREADABLE',
                'Cannot read modules directory: ' . $directoryPath,
            );
        }

        return $entryNames;
    }
}
