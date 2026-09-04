<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Core\Mail\Container\MailContainer;
use Mifrial\Core\Mail\Interface\Container\IMailContainer;
use Mifrial\Core\Mail\Interface\Service\IMail;
use Mifrial\Core\Mail\Service\MailFlushHandler;
use Mifrial\Core\Mail\Service\MailPortFactory;

return [
    'container' => MailContainer::class,
    'locator' => IMailContainer::class,
    'setup' => static function (IServiceLocator $serviceLocator) {
        return (new MailPortFactory())->createSetup($serviceLocator);
    },
    'ports' => [
        IMail::class => static function (IServiceLocator $serviceLocator): IMail {
            return (new MailPortFactory())->create($serviceLocator);
        },
        MailFlushHandler::class => static function (IServiceLocator $serviceLocator): MailFlushHandler {
            return (new MailPortFactory())->createHandler($serviceLocator);
        },
    ],
    'agents' => [
        'mail.flush' => MailFlushHandler::class,
    ],
    'routes' => [],
    'events' => [],
];
