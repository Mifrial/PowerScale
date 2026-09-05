<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

use JsonException;
use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Exception\MifrialException;
use Mifrial\Core\Kernel\Http\CsrfGuard;
use Mifrial\Core\Kernel\Http\DebugResponseFormatter;
use Mifrial\Core\Kernel\Http\ResponseEmitter;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;
use Mifrial\Core\Kernel\Interface\Http\IRequestBinder;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
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
        $this->prepareHttp($httpRequest);
        $this->responseEmitter->emitJson($this->respondTo($httpRequest));
    }

    /**
     * Копирует cookie запроса в контекст и вызывает request_bind.
     *
     * @param IHttpRequest $httpRequest Снимок входящего запроса.
     *
     * @return void
     */
    public function prepareHttp(IHttpRequest $httpRequest): void
    {
        $this->responseEmitter->beginRequest($httpRequest);
        $this->bindRequestActors();
    }

    /**
     * Отправляет JSON-конверт и завершает процесс.
     *
     * @param ActionResponse $response Конверт.
     *
     * @return never Управление не возвращается.
     */
    public function emitJson(ActionResponse $response): never
    {
        $this->responseEmitter->emitJson($response);
    }

    /**
     * Пишет отказ HTTP: до потока — JSON-конверт; после start() — только лог.
     *
     * @param IHttpRequest $httpRequest Снимок.
     * @param Throwable $throwable Отказ.
     * @param bool $streamStarted True, если SSE-заголовки уже ушли.
     *
     * @return void После потока управление возвращается; до потока — never через emitJson.
     */
    public function emitHttpError(
        IHttpRequest $httpRequest,
        Throwable $throwable,
        bool $streamStarted = false,
    ): void {
        if ($streamStarted) {
            $this->logUnhandled($httpRequest, $throwable);

            return;
        }

        if ($throwable instanceof ActionException) {
            $this->responseEmitter->emitJson(ActionResponse::fail(
                $throwable->getErrorCode(),
                $throwable->getMessage(),
            ));
        }

        $this->logUnhandled($httpRequest, $throwable);
        $this->responseEmitter->emitJson($this->debugFormatter()->internalError($throwable));
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
     * Возвращает менеджер модулей. Для CLI setup, не для HTTP-соседей.
     *
     * @return IModuleManager Менеджер.
     */
    public function getModuleManager(): IModuleManager
    {
        return $this->modules;
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
            $this->logUnhandled($httpRequest, $throwable);

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
     * Вызывает request_bind загруженных модулей.
     *
     * @return void
     */
    private function bindRequestActors(): void
    {
        $kernelContainer = $this->locator->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        if (!$requestContext instanceof IRequestContext) {
            return;
        }

        foreach ($this->modules->getLoadedModules() as $loadedModule) {
            $this->bindModuleActor($loadedModule, $requestContext);
        }
    }

    /**
     * Вызывает binder одного модуля, если ключ задан.
     *
     * @param array{group: string, name: string, config: array<string, mixed>} $loadedModule Модуль.
     * @param IRequestContext $requestContext Контекст.
     *
     * @return void
     */
    private function bindModuleActor(array $loadedModule, IRequestContext $requestContext): void
    {
        $requestBind = $loadedModule['config']['request_bind'] ?? null;
        if (!is_string($requestBind) || $requestBind === '') {
            return;
        }

        $binder = $this->modules->getContainer($loadedModule['group'], $loadedModule['name'])->get($requestBind);
        if ($binder instanceof IRequestBinder) {
            $binder->bind($requestContext);
        }
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

    /**
     * Пишет INTERNAL в логер.
     *
     * @param IHttpRequest $httpRequest Снимок.
     * @param Throwable $throwable Непойманное.
     *
     * @return void
     */
    private function logUnhandled(IHttpRequest $httpRequest, Throwable $throwable): void
    {
        $context = [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
        ];
        $actionCode = $httpRequest->getQueryValue('action');
        if (is_string($actionCode) && $actionCode !== '') {
            $context['source'] = $actionCode;
        }

        if ($throwable instanceof MifrialException) {
            $context['errorCode'] = $throwable->getErrorCode();
        }

        $requestActor = $this->findRequestActor();
        if ($requestActor instanceof RequestActor) {
            $context['userId'] = $requestActor->getUserId();
        }

        $this->logger->error('Unhandled kernel error', $context);
    }

    /**
     * Актор текущего запроса, если контекст собран.
     *
     * @return RequestActor|null Актор.
     */
    private function findRequestActor(): ?RequestActor
    {
        if (!$this->locator->has(IKernelContainer::class)) {
            return null;
        }

        $requestContext = $this->locator->get(IKernelContainer::class)->get(IRequestContext::class);
        if (!$requestContext instanceof IRequestContext) {
            return null;
        }

        return $requestContext->getActor();
    }
}
