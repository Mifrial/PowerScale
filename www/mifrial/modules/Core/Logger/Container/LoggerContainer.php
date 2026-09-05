<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Container;

use Mifrial\Core\Kernel\Container\ModuleContainer;
use Mifrial\Core\Logger\Interface\Container\ILoggerContainer;

/**
 * Контейнер портов модуля Logger.
 */
final class LoggerContainer extends ModuleContainer implements ILoggerContainer
{
}
