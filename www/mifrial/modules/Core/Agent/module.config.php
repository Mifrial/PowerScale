<?php

declare(strict_types=1);

use Mifrial\Core\Agent\Container\AgentContainer;
use Mifrial\Core\Agent\Interface\Container\IAgentContainer;
use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Agent\Service\AgentPortFactory;
use Mifrial\Core\Agent\Setup\AgentModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

return [
    'container' => AgentContainer::class,
    'locator' => IAgentContainer::class,
    'setup' => AgentModuleSetup::class,
    'ports' => [
        IAgents::class => static function (IServiceLocator $serviceLocator): IAgents {
            return (new AgentPortFactory())->create($serviceLocator);
        },
    ],
    'routes' => [],
    'events' => [],
];
