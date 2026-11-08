<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Exception;

use Mifrial\Core\Kernel\Exception\ActionException;

/**
 * База ошибок заметок: код плюс ветка наследников.
 */
abstract class NoteException extends ActionException
{
}
