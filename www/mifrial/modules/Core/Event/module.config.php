<?php

declare(strict_types=1);

use Mifrial\Core\Event\Container\EventContainer;
use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Event\Service\EventPortFactory;

return [
    'container' => EventContainer::class,
    'locator' => IEventContainer::class,
    'ports' => [
        IEventManager::class => static fn (): IEventManager => (new EventPortFactory())->create(),
    ],
    'routes' => [],
    'events' => [],
];
