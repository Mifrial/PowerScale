<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

use Mifrial\Core\Agent\Interface\Service\IAgentHandler;

/**
 * Тик агента `mail.flush`.
 */
final class MailFlushHandler implements IAgentHandler
{
    /**
     * Создаёт обработчик.
     *
     * @param MailFlushService $mailFlushService Flush.
     *
     * @return void
     */
    public function __construct(
        private readonly MailFlushService $mailFlushService,
    ) {
    }

    /**
     * Сбрасывает pending.
     *
     * @return void
     */
    public function run(): void
    {
        $this->mailFlushService->flush();
    }
}
