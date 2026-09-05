<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Rule\Container\RuleContainer;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Service\RulePortFactory;
use Mifrial\Roleplay\Rule\Setup\RuleModuleSetup;

return [
    'container' => RuleContainer::class,
    'locator' => IRuleContainer::class,
    'setup' => RuleModuleSetup::class,
    'ports' => [
        IRules::class => static function (IServiceLocator $serviceLocator): IRules {
            return (new RulePortFactory())->create($serviceLocator);
        },
    ],
    'routes' => [],
    'events' => [],
];
