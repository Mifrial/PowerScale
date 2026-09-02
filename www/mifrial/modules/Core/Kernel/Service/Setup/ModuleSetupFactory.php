<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service\Setup;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Service\Application;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;

/**
 * Собирает прогон setup из CLI-приложения.
 */
final class ModuleSetupFactory
{
    /**
     * Создаёт runner из bootSetup.
     *
     * @param Application $application Приложение со всеми модулями диска.
     *
     * @return ModuleSetupRunner Прогон.
     *
     * @throws SetupException Если шлюз SmartTable недоступен.
     */
    public function create(Application $application): ModuleSetupRunner
    {
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new SetupException('SETUP_INVALID', 'SmartTable gateway is missing');
        }

        $moduleSetups = (new ModuleSetupCollector())->collect(
            $application->getModuleManager(),
            $application->getLocator(),
        );

        return new ModuleSetupRunner($smartTableGateway, $moduleSetups);
    }
}
