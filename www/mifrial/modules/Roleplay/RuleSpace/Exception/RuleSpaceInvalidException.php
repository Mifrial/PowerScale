<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Exception;

use Throwable;

/**
 * Код, имя или состав мира недопустимы.
 */
final class RuleSpaceInvalidException extends RuleSpaceException
{
    /**
     * Создаёт ошибку недопустимого входа.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Rule space values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('RULESPACE_INVALID', $message, $previous);
    }
}
