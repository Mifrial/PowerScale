<?php

declare(strict_types=1);

namespace Mifrial\API;

use Mifrial\Core\File\Service\FileByteEmitter;
use Mifrial\Core\File\Service\FilePortFactory;
use Mifrial\Core\Kernel\Http\HttpRequest;
use Mifrial\Core\Kernel\Service\Application;
use Throwable;

/**
 * @var Application $application
 */
$application = require_once __DIR__ . '/../init.php';
$httpRequest = HttpRequest::fromGlobals();
$application->prepareHttp($httpRequest);

try {
    $publicId = $httpRequest->getQueryValue('publicId');
    (new FilePortFactory())
        ->fromContainer($application->getLocator())
        ->sendBytes(
            is_string($publicId) ? $publicId : '',
            $httpRequest->getHeader('If-None-Match'),
        );
} catch (Throwable $throwable) {
    // Байтовый ответ не JSON: только лог; после начала потока статус уже не отправить.
    $application->emitHttpError($httpRequest, $throwable, true);
    if (!headers_sent()) {
        (new FileByteEmitter())->emitError(500);
    }
}
