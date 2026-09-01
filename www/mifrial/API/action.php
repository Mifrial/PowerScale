<?php

declare(strict_types=1);

namespace Mifrial\API;

use Mifrial\Core\Kernel\Http\HttpRequest;
use Mifrial\Core\Kernel\Service\Application;

/** @var Application $application */
$application = require_once __DIR__ . '/../init.php';
$application->handle(HttpRequest::fromGlobals());
