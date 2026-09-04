<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Setup;

use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;

/**
 * Строка агента `mail.flush`.
 */
final class EnsureMailFlushAgentStep implements ISetupStep
{
    /**
     * Создаёт шаг.
     *
     * @param IAgents $agents Фасад Agent.
     *
     * @return void
     */
    public function __construct(
        private readonly IAgents $agents,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Core/Mail:ensure.mail-flush-agent';
    }

    /**
     * Пишет агент, если кода нет.
     *
     * @return void
     */
    public function run(): void
    {
        $this->agents->ensureAgent('mail.flush', 60);
    }
}
