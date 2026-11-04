<?php

declare(strict_types=1);

namespace Mifrial\API;

use Mifrial\Core\File\Dto\IncomingUpload;
use Mifrial\Core\File\Service\FilePortFactory;
use Mifrial\Core\Kernel\Dto\ActionResponse;
use Mifrial\Core\Kernel\Http\CsrfGuard;
use Mifrial\Core\Kernel\Http\HttpRequest;
use Mifrial\Core\Kernel\Service\Application;
use Throwable;

/**
 * @var Application $application
 */
$application = require_once __DIR__ . '/../init.php';
$httpRequest = HttpRequest::fromGlobals();
$application->prepareHttp($httpRequest);
$csrfGuard = new CsrfGuard();
if (!$csrfGuard->isValid($httpRequest)) {
    $application->emitJson(ActionResponse::fail('CSRF', 'CSRF token is invalid'));
}

try {
    $fileHttpService = (new FilePortFactory())->fromContainer($application->getLocator());
    $application->emitJson(ActionResponse::ok(
        $fileHttpService->upload(IncomingUpload::fromRequest($httpRequest)),
    ));
} catch (Throwable $throwable) {
    $application->emitHttpError($httpRequest, $throwable);
}
