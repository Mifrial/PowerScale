<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Exception\ModuleManager\InvalidModuleConfigException;

/**
 * Проверка и сборка карты маршрутов модулей.
 */
final class ModuleRouteCatalog
{
    /**
     * Собирает карту маршрутов загруженных модулей.
     *
     * @param array<string, array{group: string, name: string, config: array<string, mixed>}> $loadedModules Модули.
     *
     * @return array<string, array{handler: string, group: string, name: string, csrf?: bool}> Карта маршрутов.
     *
     * @throws InvalidModuleConfigException Если маршруты некорректны или дублируются.
     */
    public function collect(array $loadedModules): array
    {
        $routes = [];
        foreach ($loadedModules as $loadedModule) {
            $this->appendModule($routes, $loadedModule);
        }

        return $routes;
    }

    /**
     * Проверяет маршруты нового модуля до сохранения.
     *
     * @param string $moduleGroup Группа модуля.
     * @param string $moduleName Имя модуля.
     * @param array<string, mixed> $moduleConfig Конфигурация модуля.
     * @param array<int, string> $existingActions Уже занятые коды action.
     *
     * @return void
     *
     * @throws InvalidModuleConfigException Если маршруты некорректны или дублируются.
     */
    public function assertNewModuleRoutes(
        string $moduleGroup,
        string $moduleName,
        array $moduleConfig,
        array $existingActions,
    ): void {
        $moduleRoutes = $moduleConfig['routes'] ?? [];
        if (!is_array($moduleRoutes)) {
            throw new InvalidModuleConfigException($moduleGroup, $moduleName, 'routes must be an array');
        }

        foreach ($moduleRoutes as $routeAction => $routeSpecification) {
            if (!$this->isValidRoute($routeAction, $routeSpecification)) {
                throw new InvalidModuleConfigException(
                    $moduleGroup,
                    $moduleName,
                    'Invalid route: ' . (is_string($routeAction) ? $routeAction : '?'),
                );
            }

            if (in_array($routeAction, $existingActions, true)) {
                throw new InvalidModuleConfigException(
                    $moduleGroup,
                    $moduleName,
                    'Duplicate action: ' . $routeAction,
                );
            }
        }
    }

    /**
     * Добавляет маршруты одного модуля.
     *
     * @param array<string, array{handler: string, group: string, name: string, csrf?: bool}> $routes Карта.
     * @param array{group: string, name: string, config: array<string, mixed>} $loadedModule Модуль.
     *
     * @return void
     *
     * @throws InvalidModuleConfigException Если маршрут некорректен или дублируется.
     */
    private function appendModule(array &$routes, array $loadedModule): void
    {
        $moduleRoutes = $loadedModule['config']['routes'] ?? [];
        if (!is_array($moduleRoutes)) {
            throw new InvalidModuleConfigException(
                $loadedModule['group'],
                $loadedModule['name'],
                'routes must be an array',
            );
        }

        foreach ($moduleRoutes as $routeAction => $routeSpecification) {
            $this->registerRoute($routes, $loadedModule, $routeAction, $routeSpecification);
        }
    }

    /**
     * Добавляет один маршрут.
     *
     * @param array<string, array{handler: string, group: string, name: string, csrf?: bool}> $routes Карта.
     * @param array{group: string, name: string, config: array<string, mixed>} $loadedModule Модуль.
     * @param mixed $routeAction Код действия.
     * @param mixed $routeSpecification Описание маршрута.
     *
     * @return void
     *
     * @throws InvalidModuleConfigException Если маршрут некорректен или дублируется.
     */
    private function registerRoute(
        array &$routes,
        array $loadedModule,
        mixed $routeAction,
        mixed $routeSpecification,
    ): void {
        if (!$this->isValidRoute($routeAction, $routeSpecification)) {
            throw new InvalidModuleConfigException(
                $loadedModule['group'],
                $loadedModule['name'],
                'Invalid route: ' . (is_string($routeAction) ? $routeAction : '?'),
            );
        }

        if (isset($routes[$routeAction])) {
            throw new InvalidModuleConfigException(
                $loadedModule['group'],
                $loadedModule['name'],
                'Duplicate action: ' . $routeAction,
            );
        }

        $routes[$routeAction] = [
            'handler' => $routeSpecification['handler'],
            'group' => $loadedModule['group'],
            'name' => $loadedModule['name'],
            'csrf' => $routeSpecification['csrf'] ?? true,
        ];
    }

    /**
     * Проверяет структуру описания маршрута.
     *
     * @param mixed $routeAction Код действия.
     * @param mixed $routeSpecification Описание маршрута.
     *
     * @return bool true, если маршрут можно зарегистрировать.
     */
    private function isValidRoute(mixed $routeAction, mixed $routeSpecification): bool
    {
        return is_string($routeAction)
            && is_array($routeSpecification)
            && isset($routeSpecification['handler'])
            && is_string($routeSpecification['handler']);
    }
}
