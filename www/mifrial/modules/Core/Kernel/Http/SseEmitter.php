<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Http;

use Closure;
use JsonException;
use Mifrial\Core\Kernel\Exception\KernelException;

/**
 * Байты SSE: заголовки потока, событие, комментарий. Не знает Chat.
 */
final class SseEmitter
{
    private bool $started = false;

    /**
     * Создаёт отправитель потока.
     *
     * @param Closure|null $write Приёмник чанка; null — echo.
     * @param bool $sendHeaders False в тестах без SAPI-заголовков.
     *
     * @return void
     */
    public function __construct(
        private readonly ?Closure $write = null,
        private readonly bool $sendHeaders = true,
    ) {
    }

    /**
     * Заголовки event-stream и отключение лимита времени.
     *
     * @return void
     */
    public function start(): void
    {
        $this->started = true;
        if (!$this->sendHeaders) {
            return;
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        ignore_user_abort(true);
        set_time_limit(0);
        $this->clearOutputBuffers();
    }

    /**
     * Поток уже открыт: JSON-конверт слать нельзя.
     *
     * @return bool True после start().
     */
    public function hasStarted(): bool
    {
        return $this->started;
    }

    /**
     * Пишет именованное событие с JSON-телом.
     *
     * @param string $eventName Имя SSE-события.
     * @param array<string, mixed> $payload Тело.
     *
     * @return void
     *
     * @throws KernelException Если JSON не сериализуется.
     */
    public function writeEvent(string $eventName, array $payload): void
    {
        $this->output('event: ' . $eventName . "\ndata: " . $this->encodeJson($payload) . "\n\n");
        $this->flush();
    }

    /**
     * Пишет комментарий (heartbeat), курсор не двигает.
     *
     * @param string $text Текст после `: `.
     *
     * @return void
     */
    public function writeComment(string $text): void
    {
        $this->output(': ' . $text . "\n\n");
        $this->flush();
    }

    /**
     * JSON тела события.
     *
     * @param array<string, mixed> $payload Тело.
     *
     * @return string JSON.
     *
     * @throws KernelException Если сериализация невозможна.
     */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new KernelException('INTERNAL', 'SSE JSON is invalid', $exception);
        }
    }

    /**
     * Снимает буферы вывода, чтобы flush дошёл до клиента.
     *
     * @return void
     */
    private function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * Отдаёт чанк в SAPI или тест.
     *
     * @param string $chunk Байты.
     *
     * @return void
     */
    private function output(string $chunk): void
    {
        if ($this->write instanceof Closure) {
            ($this->write)($chunk);

            return;
        }

        echo $chunk;
    }

    /**
     * Сбрасывает буфер SAPI.
     *
     * @return void
     */
    private function flush(): void
    {
        if ($this->write instanceof Closure) {
            return;
        }

        flush();
    }
}
