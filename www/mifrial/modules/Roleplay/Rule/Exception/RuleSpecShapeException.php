<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Exception;

/**
 * Форма одного ключа spec не легла в контракт типа.
 */
final class RuleSpecShapeException extends RuleException
{
    /**
     * Создаёт отказ формы.
     *
     * @param string $message Уточнение.
     *
     * @return void
     */
    public function __construct(string $message = 'Rule spec shape is invalid')
    {
        parent::__construct('RULE_SPEC_SHAPE', $message);
    }
}
