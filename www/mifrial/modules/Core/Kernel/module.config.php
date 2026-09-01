<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Action\PingAction;
use Mifrial\Core\Kernel\Interface\Service\IKernelContainer;
use Mifrial\Core\Kernel\Service\KernelContainer;

return [
    'container' => KernelContainer::class,
    'locator' => IKernelContainer::class,
    'ports' => [
        PingAction::class => static fn (): PingAction => new PingAction(),
    ],
    'routes' => [
        'mifrial.ping' => [
            'handler' => PingAction::class,
            'csrf' => false,
        ],
    ],
    'events' => [],
];
