<?php

declare(strict_types=1);

namespace Mifrial\API;

use Mifrial\Core\Kernel\Http\HttpRequest;
use Mifrial\Core\Kernel\Http\SseEmitter;
use Mifrial\Core\Kernel\Service\Application;
use Mifrial\Messages\Chat\Service\ChatPortFactory;
use Throwable;

/** @var Application $application */
$application = require_once __DIR__ . '/../init.php';
$httpRequest = HttpRequest::fromGlobals();
$application->prepareHttp($httpRequest);
$sseEmitter = new SseEmitter();

try {
    (new ChatPortFactory())->fromContainerSse($application->getLocator())->run(
        $httpRequest,
        $sseEmitter,
    );
} catch (Throwable $throwable) {
    $application->emitHttpError($httpRequest, $throwable, $sseEmitter->hasStarted());
}
