<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\CliFailureReporter;
use Mifrial\Core\Kernel\Service\Setup\ModuleSetupFactory;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$application = (new ApplicationFactory())->bootSetup($root);
try {
    (new ModuleSetupFactory())->create($application)->run();
} catch (Throwable $throwable) {
    (new CliFailureReporter())->reportFromApplication($application, 'mifrial.setup', $throwable);
    exit(1);
}
