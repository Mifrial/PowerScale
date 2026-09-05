<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Exception;

use Throwable;

/**
 * Мир или ревизия не найдены.
 */
final class RuleSpaceNotFoundException extends RuleSpaceException
{
    /**
     * Создаёт ошибку отсутствия.
     *
     * @param string $message Уточнение.
     * @param Throwable|null $previous Исходное исключение.
     *
     * @return void
     */
    public function __construct(
        string $message = 'Rule space was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('RULESPACE_NOT_FOUND', $message, $previous);
    }
}
