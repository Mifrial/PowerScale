<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Dto;

/**
 * Конверт ответа действия для JSON.
 */
final class ActionResponse
{
    /**
     * Создаёт ответ действия с указанным статусом и данными.
     *
     * @param bool $success Признак успешного выполнения.
     * @param mixed $data    Данные успешного ответа.
     * @param array<string, mixed>|null $error   Код и описание ошибки.
     *
     * @return void
     */
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?array $error = null,
    ) {
    }

    /**
     * Преобразует ответ в массив для JSON-сериализации.
     *
     * @return array{success: bool, data: mixed, error?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $payload = [
            'success' => $this->success,
            'data' => $this->data,
        ];

        if ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }

    /**
     * Создаёт успешный ответ с данными.
     *
     * @param mixed $data Данные успешного ответа.
     *
     * @return self Успешный ответ.
     */
    public static function ok(mixed $data): self
    {
        return new self(true, $data, null);
    }

    /**
     * Создаёт ответ с ошибкой.
     *
     * @param string $errorCode Машиночитаемый код ошибки.
     * @param string $message Сообщение об ошибке.
     * @param array<string, mixed> $details Дополнительные поля ошибки.
     *
     * @return self Ответ с ошибкой.
     */
    public static function fail(string $errorCode, string $message, array $details = []): self
    {
        return new self(false, null, array_merge(['code' => $errorCode, 'message' => $message], $details));
    }
}
