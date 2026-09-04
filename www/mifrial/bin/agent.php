<?php

declare(strict_types=1);

use Mifrial\Core\Agent\Service\AgentTickCli;
use Mifrial\Core\Kernel\Service\ApplicationFactory;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$application = (new ApplicationFactory())->bootSetup($root);
(new AgentTickCli())->run(
    $application->getModuleManager()->getLoadedModules(),
    $application->getLocator(),
);
