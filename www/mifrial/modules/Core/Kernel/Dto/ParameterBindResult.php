<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Результат биндинга параметров handle.
 */
final class ParameterBindResult
{
    /**
     * Создаёт результат привязки аргументов handle.
     *
     * @param array<int, mixed>|null $arguments Позиционные аргументы при успехе.
     * @param ActionResponse|null $errorResponse Ответ с ошибкой при неуспехе.
     *
     * @return void
     */
    private function __construct(
        private readonly ?array $arguments,
        private readonly ?ActionResponse $errorResponse,
    ) {
    }

    /**
     * Создаёт успешную привязку аргументов.
     *
     * @param array<int, mixed> $arguments Позиционные аргументы handle.
     *
     * @return self Успешный результат.
     */
    public static function ok(array $arguments): self
    {
        return new self($arguments, null);
    }

    /**
     * Создаёт ошибку привязки параметров.
     *
     * @param string $code Машиночитаемый код ошибки.
     * @param string $message Описание ошибки.
     *
     * @return self Результат с ошибкой.
     */
    public static function fail(string $code, string $message): self
    {
        return new self(null, ActionResponse::fail($code, $message));
    }

    /**
     * Проверяет, что аргументы успешно собраны.
     *
     * @return bool true, если можно вызывать handle.
     */
    public function isOk(): bool
    {
        return $this->errorResponse === null;
    }

    /**
     * Возвращает позиционные аргументы handle.
     *
     * @return array<int, mixed> Аргументы в порядке параметров метода.
     */
    public function arguments(): array
    {
        return $this->arguments ?? [];
    }

    /**
     * Возвращает ответ с ошибкой привязки.
     *
     * @return ActionResponse Конверт ошибки параметров.
     */
    public function errorResponse(): ActionResponse
    {
        return $this->errorResponse ?? ActionResponse::fail('INTERNAL', 'Internal error');
    }
}
