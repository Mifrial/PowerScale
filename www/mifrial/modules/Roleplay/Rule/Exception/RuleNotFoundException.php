<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Exception;

use Throwable;

/**
 * Правило, пространство или ревизия не найдены.
 */
final class RuleNotFoundException extends RuleException
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
        string $message = 'Rule record was not found',
        ?Throwable $previous = null,
    ) {
        parent::__construct('RULE_NOT_FOUND', $message, $previous);
    }
}
