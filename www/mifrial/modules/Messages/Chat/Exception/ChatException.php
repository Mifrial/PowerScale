<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Exception;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * База ошибок чата: код плюс ветка наследников.
 */
abstract class ChatException extends ActionException
{
}
