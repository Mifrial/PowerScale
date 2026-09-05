<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Exception;

use Throwable;

/**
 * Вход или инвариант правила недопустимы.
 */
final class RuleInvalidException extends RuleException
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
        string $message = 'Rule values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('RULE_INVALID', $message, $previous);
    }
}
