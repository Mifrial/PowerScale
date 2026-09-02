<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Container;

use Mifrial\Core\Auth\Interface\Container\IAuthContainer;
use Mifrial\Core\Kernel\Container\ModuleContainer;

/**
 * Контейнер портов модуля Auth.
 */
final class AuthContainer extends ModuleContainer implements IAuthContainer
{
}
