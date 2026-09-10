<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Exception;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * База ошибок HTTP журнала.
 */
abstract class LoggerException extends ActionException
{
}
