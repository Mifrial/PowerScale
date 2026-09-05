<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Exception;

use Throwable;

/**
 * Код, имя или значения признака недопустимы.
 */
final class KeywordInvalidException extends KeywordException
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
        string $message = 'Keyword values are invalid',
        ?Throwable $previous = null,
    ) {
        parent::__construct('KEYWORD_INVALID', $message, $previous);
    }
}
