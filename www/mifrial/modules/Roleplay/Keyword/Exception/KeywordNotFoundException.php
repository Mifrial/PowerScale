<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Exception;

use Throwable;

/**
 * Строка признака не найдена.
 */
final class KeywordNotFoundException extends KeywordException
{
    /**
     * Создаёт ошибку отсутствия строки.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Keyword was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('KEYWORD_NOT_FOUND', $message, $previous);
    }
}
