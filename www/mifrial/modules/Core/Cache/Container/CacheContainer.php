<?php

declare(strict_types=1);

namespace Mifrial\Core\Cache\Container;

use Mifrial\Core\Cache\Interface\Container\ICacheContainer;
use Mifrial\Core\Kernel\Container\ModuleContainer;

/**
 * Контейнер портов модуля Cache.
 */
final class CacheContainer extends ModuleContainer implements ICacheContainer
{
}
