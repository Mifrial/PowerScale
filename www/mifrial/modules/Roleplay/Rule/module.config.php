<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Rule\Container\RuleContainer;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Interface\Service\IRules;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Service\RulePortFactory;
use Mifrial\Roleplay\Rule\Setup\RuleModuleSetup;

return [
    'container' => RuleContainer::class,
    'locator' => IRuleContainer::class,
    'setup' => static function (IServiceLocator $serviceLocator): RuleModuleSetup {
        $smartTableGateway = $serviceLocator->get(ISmartTableContainer::class)->get(ISmartTableGateway::class);
        if (!$smartTableGateway instanceof ISmartTableGateway) {
            throw new KernelException('PORT_TYPE', 'Rule setup requires ISmartTableGateway');
        }

        return new RuleModuleSetup($smartTableGateway);
    },
    'ports' => [
        IRules::class => static function (IServiceLocator $serviceLocator): IRules {
            return (new RulePortFactory())->create($serviceLocator);
        },
        IFormulaEvaluations::class => static function (): IFormulaEvaluations {
            return new FormulaEvaluations();
        },
    ],
    'routes' => [],
    'events' => [],
];
