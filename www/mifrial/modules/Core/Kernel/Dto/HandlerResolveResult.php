<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Action\IActionHandler;

/**
 * Результат поиска обработчика действия.
 */
final class HandlerResolveResult
{
    /**
     * Создаёт результат поиска обработчика.
     *
     * @param IActionHandler|null $handler Найденный обработчик.
     * @param ActionResponse|null $errorResponse Ошибка конфигурации маршрута.
     *
     * @return void
     */
    private function __construct(
        private readonly ?IActionHandler $handler,
        private readonly ?ActionResponse $errorResponse,
    ) {
    }

    /**
     * Создаёт успешный результат.
     *
     * @param IActionHandler $handler Обработчик действия.
     *
     * @return self Успешный результат.
     */
    public static function ok(IActionHandler $handler): self
    {
        return new self($handler, null);
    }

    /**
     * Создаёт ошибку поиска обработчика.
     *
     * @param ActionResponse $errorResponse Конверт ошибки.
     *
     * @return self Результат с ошибкой.
     */
    public static function fail(ActionResponse $errorResponse): self
    {
        return new self(null, $errorResponse);
    }

    /**
     * Проверяет, что обработчик найден.
     *
     * @return bool true, если можно вызывать handle.
     */
    public function isOk(): bool
    {
        return $this->handler instanceof IActionHandler;
    }

    /**
     * Возвращает найденный обработчик.
     *
     * @return IActionHandler Обработчик действия.
     *
     * @throws KernelException Если обработчик не был найден.
     */
    public function handler(): IActionHandler
    {
        if (!$this->handler instanceof IActionHandler) {
            throw new KernelException('INTERNAL', 'Handler was not resolved');
        }

        return $this->handler;
    }

    /**
     * Возвращает конверт ошибки поиска.
     *
     * @return ActionResponse Конверт ошибки.
     */
    public function errorResponse(): ActionResponse
    {
        return $this->errorResponse ?? ActionResponse::fail('INTERNAL', 'Internal error');
    }
}
