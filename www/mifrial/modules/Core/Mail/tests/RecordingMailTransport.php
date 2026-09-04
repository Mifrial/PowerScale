<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Tests;

use Mifrial\Core\Mail\Service\IMailTransport;
use RuntimeException;

/**
 * Транспорт, который копит письма.
 */
final class RecordingMailTransport implements IMailTransport
{
    /**
     * @var array<int, array{from: string, to: string, subject: string, body: string}>
     */
    public array $sent = [];

    public bool $failNext = false;

    public ?int $failOnCall = null;

    private int $sendCalls = 0;

    /**
     * Копит или бросает.
     *
     * @param string $emailFrom От кого.
     * @param string $emailTo Кому.
     * @param string $subject Тема.
     * @param string $body Тело.
     *
     * @return void
     */
    public function send(string $emailFrom, string $emailTo, string $subject, string $body): void
    {
        $this->sendCalls++;
        if ($this->failNext || $this->failOnCall === $this->sendCalls) {
            $this->failNext = false;
            throw new RuntimeException('transport down');
        }

        $this->sent[] = [
            'from' => $emailFrom,
            'to' => $emailTo,
            'subject' => $subject,
            'body' => $body,
        ];
    }
}
