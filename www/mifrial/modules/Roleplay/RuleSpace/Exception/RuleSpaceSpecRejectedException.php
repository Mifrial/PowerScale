<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Exception;

/**
 * Публикация ревизии отклонена: spec состава не легла в контракт.
 */
final class RuleSpaceSpecRejectedException extends RuleSpaceException
{
    /**
     * Создаёт отказ.
     *
     * @param array<int, array{ruleCode: string, ruleName: string, messages: array<int, string>}> $problems Правила.
     *
     * @return void
     */
    public function __construct(
        private readonly array $problems,
    ) {
        parent::__construct('RULESPACE_INVALID', 'Rule revision spec is invalid');
    }

    /**
     * Проблемы состава.
     *
     * @return array<string, mixed> Поле problems.
     */
    public function getErrorDetails(): array
    {
        return ['problems' => $this->problems];
    }
}
