<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Interface\Service;

use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Interface\Http\IHttpRequest;

/**
 * Контракт фасада процесса: HTTP и внутренний dispatch.
 */
interface IApplication
{
    /**
     * Обрабатывает HTTP-запрос и завершает процесс ответом.
     *
     * @param IHttpRequest $httpRequest Снимок входящего запроса.
     *
     * @return never Управление не возвращается.
     */
    public function handle(IHttpRequest $httpRequest): never;

    /**
     * Выполняет action без отправки HTTP-ответа.
     *
     * @param string $action Код действия.
     * @param mixed $payload Полезная нагрузка.
     *
     * @return ActionResponse Результат действия.
     */
    public function dispatch(string $action, mixed $payload): ActionResponse;

    /**
     * Возвращает локальную конфигурацию приложения.
     *
     * @return array<string, mixed> Конфигурация приложения.
     */
    public function getConfig(): array;

    /**
     * Возвращает каталог контейнеров модулей.
     *
     * @return IServiceLocator Каталог контейнеров.
     */
    public function getLocator(): IServiceLocator;
}
