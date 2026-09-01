<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Service\ApplicationFactory;

require_once __DIR__ . '/vendor/autoload.php';

return (new ApplicationFactory())->boot(__DIR__);
