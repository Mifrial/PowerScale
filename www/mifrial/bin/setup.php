<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Service\Setup\ModuleSetupFactory;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$application = (new ApplicationFactory())->bootSetup($root);
(new ModuleSetupFactory())->create($application)->run();
