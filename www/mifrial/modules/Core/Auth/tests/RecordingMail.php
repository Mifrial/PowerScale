<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Mail\Interface\Service\IMail;

/**
 * IMail, который помнит последний trigger.
 */
final class RecordingMail implements IMail
{
    public ?string $eventCode = null;

    /**
     * @var array<string, mixed>
     */
    public array $payload = [];

    public int $nextJobId = 7;

    public ?int $flushedJobId = null;

    /**
     * Запоминает вызов.
     *
     * @param string $eventCode Код.
     * @param array<string, mixed> $payload Поля.
     *
     * @return void
     */
    public function trigger(string $eventCode, array $payload): void
    {
        $this->flush($this->enqueue($eventCode, $payload));
    }

    /**
     * Запоминает постановку.
     *
     * @param string $eventCode Код.
     * @param array<string, mixed> $payload Поля.
     *
     * @return int Id job.
     */
    public function enqueue(string $eventCode, array $payload): int
    {
        $this->eventCode = $eventCode;
        $this->payload = $payload;

        return $this->nextJobId;
    }

    /**
     * Запоминает flush.
     *
     * @param int $jobId Id job.
     *
     * @return void
     */
    public function flush(int $jobId): void
    {
        $this->flushedJobId = $jobId;
    }
}
