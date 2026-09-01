<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use JsonException;
use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Http\CsrfGuard;
use Mifrial\Core\Kernel\Http\DebugResponseFormatter;
use Mifrial\Core\Kernel\Http\ResponseEmitter;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Interface\Service\IDispatcher;
use Mifrial\Core\Kernel\Interface\Service\ILogger;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Throwable;

/**
 * HTTP-конвейер и внутренний вызов action.
 */
final class Application implements IApplication
{
    /**
     * Создаёт фасад процесса.
     *
     * @param IServiceLocator $locator Каталог контейнеров модулей.
     * @param IModuleManager $modules Менеджер модулей.
     * @param IDispatcher $dispatcher Диспетчер действий.
     * @param ResponseEmitter $responseEmitter Отправитель HTTP JSON.
     * @param ILogger $logger Логер ошибок ядра.
     * @param array<string, mixed> $config Локальная конфигурация.
     *
     * @return void
     */
    public function __construct(
        private readonly IServiceLocator $locator,
        private readonly IModuleManager $modules,
        private readonly IDispatcher $dispatcher,
        private readonly ResponseEmitter $responseEmitter,
        private readonly ILogger $logger,
        private readonly array $config,
    ) {
    }

    /**
     * Обрабатывает HTTP-запрос и завершает процесс ответом.
     *
     * @param IHttpRequest $httpRequest Снимок входящего запроса.
     *
     * @return never Управление не возвращается.
     */
    public function handle(IHttpRequest $httpRequest): never
    {
        $this->responseEmitter->emitJson($this->respondTo($httpRequest));
    }

    /**
     * Выполняет action без HTTP и CSRF.
     *
     * @param string $action Код действия.
     * @param mixed $payload Полезная нагрузка.
     *
     * @return ActionResponse Результат действия.
     */
    public function dispatch(string $action, mixed $payload): ActionResponse
    {
        return $this->debugFormatter()->maskInfrastructure(
            $this->dispatcher->dispatch($action, $payload),
        );
    }

    /**
     * Возвращает локальную конфигурацию приложения.
     *
     * @return array<string, mixed> Конфигурация приложения.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Возвращает каталог контейнеров модулей.
     *
     * @return IServiceLocator Каталог контейнеров.
     */
    public function getLocator(): IServiceLocator
    {
        return $this->locator;
    }

    /**
     * Разбирает HTTP-запрос, проверяет CSRF и выполняет action.
     *
     * @param IHttpRequest $httpRequest Снимок входящего запроса.
     *
     * @return ActionResponse Результат действия.
     */
    private function respondTo(IHttpRequest $httpRequest): ActionResponse
    {
        try {
            return $this->debugFormatter()->maskInfrastructure(
                $this->dispatchFromRequest($httpRequest),
            );
        } catch (Throwable $throwable) {
            $this->logger->error('Unhandled kernel error', [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);

            return $this->debugFormatter()->internalError($throwable);
        }
    }

    /**
     * Маршрутизирует HTTP-запрос к действию.
     *
     * @param IHttpRequest $httpRequest Снимок входящего запроса.
     *
     * @return ActionResponse Результат действия.
     */
    private function dispatchFromRequest(IHttpRequest $httpRequest): ActionResponse
    {
        $action = $httpRequest->getQueryValue('action');
        $actionCode = is_string($action) ? $action : '';
        $routeError = $this->httpRouteError($actionCode, $httpRequest);
        if ($routeError !== null) {
            return $routeError;
        }

        try {
            $payload = $httpRequest->getJsonPayload();
        } catch (JsonException) {
            return ActionResponse::fail('INVALID_JSON', 'Request body is not JSON');
        }

        return $this->dispatcher->dispatch($actionCode, $payload);
    }

    /**
     * Проверяет существование маршрута и CSRF.
     *
     * @param string $actionCode Код действия.
     * @param IHttpRequest $httpRequest Снимок запроса.
     *
     * @return ActionResponse|null Ошибка маршрута/CSRF или null.
     */
    private function httpRouteError(string $actionCode, IHttpRequest $httpRequest): ?ActionResponse
    {
        $routes = $this->modules->getRoutes();
        if (!isset($routes[$actionCode])) {
            return ActionResponse::fail('UNKNOWN_ACTION', 'Unknown action: ' . $actionCode);
        }

        $csrfRequired = ($routes[$actionCode]['csrf'] ?? true) !== false;
        if ($csrfRequired && !(new CsrfGuard())->isValid($httpRequest)) {
            return ActionResponse::fail('CSRF', 'CSRF token mismatch');
        }

        return null;
    }

    /**
     * Создаёт форматтер по флагу debug из конфигурации.
     *
     * @return DebugResponseFormatter Форматтер конверта.
     */
    private function debugFormatter(): DebugResponseFormatter
    {
        return new DebugResponseFormatter($this->config['debug'] ?? false);
    }
}
