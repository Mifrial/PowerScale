<?php

declare(strict_types=1);

use Mifrial\Core\Agent\Service\AgentTickCli;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\CliFailureReporter;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$application = (new ApplicationFactory())->bootSetup($root);
try {
    (new AgentTickCli())->run(
        $application->getModuleManager()->getLoadedModules(),
        $application->getLocator(),
    );
} catch (Throwable $throwable) {
    (new CliFailureReporter())->reportFromApplication($application, 'mifrial.agent', $throwable);
    exit(1);
}
