<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

use Mifrial\Core\Agent\Interface\Container\IAgentContainer;
use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Service\IRuntimeConfig;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Mail\Dto\MailSettings;
use Mifrial\Core\Mail\Interface\Service\IMail;
use Mifrial\Core\Mail\Repository\MailEventRepository;
use Mifrial\Core\Mail\Repository\MailJobRepository;
use Mifrial\Core\Mail\Repository\MailTemplateRepository;
use Mifrial\Core\Mail\Setup\MailModuleSetup;
use Mifrial\Core\Mail\Table\MailEventTable;
use Mifrial\Core\Mail\Table\MailJobTable;
use Mifrial\Core\Mail\Table\MailTemplateTable;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;

/**
 * Сборка портов Mail из локатора.
 */
final class MailPortFactory
{
    /**
     * Фасад соседа.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMail Фасад.
     */
    public function create(IServiceLocator $serviceLocator): IMail
    {
        $opened = $this->opened($serviceLocator);

        return new MailService(
            $opened['events'],
            $opened['jobs'],
            $opened['settings'],
            $opened['flush'],
        );
    }

    /**
     * Обработчик агента.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MailFlushHandler Handler.
     *
     * @throws KernelException Если нет шлюза или конфига.
     */
    public function createHandler(IServiceLocator $serviceLocator): MailFlushHandler
    {
        return new MailFlushHandler($this->opened($serviceLocator)['flush']);
    }

    /**
     * Setup с IAgents.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return MailModuleSetup Setup.
     *
     * @throws KernelException Если нет IAgents.
     */
    public function createSetup(IServiceLocator $serviceLocator): MailModuleSetup
    {
        $agentContainer = $serviceLocator->get(IAgentContainer::class);
        $agents = $agentContainer->get(IAgents::class);
        if (!$agents instanceof IAgents) {
            throw new KernelException('PORT_TYPE', 'Mail setup requires IAgents');
        }

        $opened = $this->opened($serviceLocator);

        return new MailModuleSetup($agents, $opened['events'], $opened['templates']);
    }

    /**
     * Репозитории и flush на одном шлюзе.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return array{
     *     events: MailEventRepository,
     *     templates: MailTemplateRepository,
     *     jobs: MailJobRepository,
     *     settings: MailSettings,
     *     flush: MailFlushService
     * } Сборка.
     *
     * @throws KernelException Если нет шлюза или конфига.
     */
    private function opened(IServiceLocator $serviceLocator): array
    {
        $gateway = $this->gateway($serviceLocator);
        $jobRepository = new MailJobRepository($gateway->open(MailJobTable::class)->records());
        $templateRepository = new MailTemplateRepository(
            $gateway->open(MailTemplateTable::class)->records(),
        );
        $kernelContainer = $serviceLocator->get(IKernelContainer::class);
        $runtimeConfig = $kernelContainer->get(IRuntimeConfig::class);
        if (!$runtimeConfig instanceof IRuntimeConfig) {
            throw new KernelException('PORT_TYPE', 'Mail requires IRuntimeConfig');
        }

        return [
            'events' => new MailEventRepository($gateway->open(MailEventTable::class)->records()),
            'templates' => $templateRepository,
            'jobs' => $jobRepository,
            'settings' => MailSettings::fromSection($runtimeConfig->section('mail')),
            'flush' => new MailFlushService(
                $jobRepository,
                $templateRepository,
                new PlaceholderRenderer(),
                new LogMailTransport(),
            ),
        ];
    }

    /**
     * Шлюз ST.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ISmartTableGateway Шлюз.
     *
     * @throws KernelException Если нет шлюза.
     */
    private function gateway(IServiceLocator $serviceLocator): ISmartTableGateway
    {
        $smartTableContainer = $serviceLocator->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Mail requires ISmartTableGateway');
        }

        return $smartTableGateway;
    }
}
