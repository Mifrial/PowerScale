<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Exception;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * База ошибок Auth с кодом для HTTP-конверта.
 */
abstract class AuthException extends ActionException
{
}
