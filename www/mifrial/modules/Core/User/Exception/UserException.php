<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Exception;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * База ошибок учётки: код плюс ветка наследников.
 */
abstract class UserException extends ActionException
{
}
