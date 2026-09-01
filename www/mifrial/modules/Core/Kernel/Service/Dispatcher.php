<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Dto\HandlerResolveResult;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;
use Mifrial\Core\Kernel\Interface\Service\IDispatcher;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use ReflectionMethod;

/**
 * Поиск обработчика и вызов handle.
 */
final class Dispatcher implements IDispatcher
{
    /**
     * Создаёт диспетчер действий.
     *
     * @param IModuleManager $modules Менеджер модулей и маршрутов.
     * @param ActionParameterBinder $parameterBinder Биндер параметров handle.
     *
     * @return void
     */
    public function __construct(
        private readonly IModuleManager $modules,
        private readonly ActionParameterBinder $parameterBinder = new ActionParameterBinder(),
    ) {
    }

    /**
     * Диспетчеризует запрос по коду действия.
     *
     * @param string $action Код действия.
     * @param mixed $payload Полезная нагрузка запроса.
     *
     * @return ActionResponse Ответ обработчика или описание ошибки.
     */
    public function dispatch(string $action, mixed $payload): ActionResponse
    {
        $routes = $this->modules->getRoutes();
        if (!isset($routes[$action])) {
            return ActionResponse::fail('UNKNOWN_ACTION', 'Unknown action: ' . $action);
        }

        $resolveResult = $this->resolveHandler($routes[$action]);
        if (!$resolveResult->isOk()) {
            return $resolveResult->errorResponse();
        }

        return $this->invokeHandle($resolveResult->handler(), $payload);
    }

    /**
     * Достаёт обработчик из контейнера модуля.
     *
     * @param array{handler: string, group: string, name: string} $routeSpecification Маршрут действия.
     *
     * @return HandlerResolveResult Обработчик или ошибка конфигурации.
     */
    private function resolveHandler(array $routeSpecification): HandlerResolveResult
    {
        $handlerPort = $routeSpecification['handler'];
        $moduleContainer = $this->modules->getContainer(
            $routeSpecification['group'],
            $routeSpecification['name'],
        );
        $handler = $moduleContainer->get($handlerPort);
        $invalidHandler = $this->invalidHandlerError($handler, $handlerPort);
        if ($invalidHandler !== null) {
            return HandlerResolveResult::fail($invalidHandler);
        }

        return HandlerResolveResult::ok($handler);
    }

    /**
     * Проверяет, что порт является публичным обработчиком.
     *
     * @param object $handler Объект порта.
     * @param string $handlerPort Ключ порта.
     *
     * @return ActionResponse|null Ошибка или null, если обработчик корректен.
     */
    private function invalidHandlerError(object $handler, string $handlerPort): ?ActionResponse
    {
        if (!$handler instanceof IActionHandler || !method_exists($handler, 'handle')) {
            return ActionResponse::fail('INVALID_HANDLER', 'Handler is not an IActionHandler: ' . $handlerPort);
        }

        $handleMethod = new ReflectionMethod($handler, 'handle');
        if ($handleMethod->isPublic()) {
            return null;
        }

        return ActionResponse::fail('INVALID_HANDLER', 'Handler handle() must be public: ' . $handlerPort);
    }

    /**
     * Биндит параметры, вызывает handle и собирает конверт.
     *
     * @param IActionHandler $handler Обработчик действия.
     * @param mixed $payload Полезная нагрузка запроса.
     *
     * @return ActionResponse Конверт успеха, параметров или доменной ошибки.
     */
    private function invokeHandle(IActionHandler $handler, mixed $payload): ActionResponse
    {
        $handleMethod = new ReflectionMethod($handler, 'handle');
        $bindResult = $this->parameterBinder->bind($handleMethod, $payload);
        if (!$bindResult->isOk()) {
            return $bindResult->errorResponse();
        }

        try {
            return ActionResponse::ok($handleMethod->invokeArgs($handler, $bindResult->arguments()));
        } catch (ActionException $actionException) {
            return ActionResponse::fail(
                $actionException->getErrorCode(),
                $actionException->getMessage(),
            );
        }
    }
}
