<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Container;

use Mifrial\Core\Event\Interface\Container\IEventContainer;
use Mifrial\Core\Kernel\Container\ModuleContainer;

/**
 * Контейнер портов модуля Event.
 */
final class EventContainer extends ModuleContainer implements IEventContainer
{
}
