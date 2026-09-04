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
        $this->eventCode = $eventCode;
        $this->payload = $payload;
    }
}
