<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Setup;

use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\Mail\Repository\MailEventRepository;
use Mifrial\Core\Mail\Repository\MailTemplateRepository;
use Mifrial\Core\Mail\Schema\MailSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Карты Mail, агент flush и seed forgot.
 */
final class MailModuleSetup implements IModuleSetup
{
    /**
     * Создаёт setup.
     *
     * @param IAgents $agents Фасад Agent.
     * @param MailEventRepository $eventRepository События.
     * @param MailTemplateRepository $templateRepository Шаблоны.
     *
     * @return void
     */
    public function __construct(
        private readonly IAgents $agents,
        private readonly MailEventRepository $eventRepository,
        private readonly MailTemplateRepository $templateRepository,
    ) {
    }

    /**
     * Возвращает карты Mail.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public function getTableClasses(): array
    {
        return MailSchema::getTableClasses();
    }

    /**
     * Регистрирует `mail.flush` и событие сброса.
     *
     * @return array<int, ISetupStep> Шаги.
     */
    public function getDataSteps(): array
    {
        return [
            new EnsureMailFlushAgentStep($this->agents),
            new EnsureAuthPasswordResetMailStep($this->eventRepository, $this->templateRepository),
        ];
    }
}
