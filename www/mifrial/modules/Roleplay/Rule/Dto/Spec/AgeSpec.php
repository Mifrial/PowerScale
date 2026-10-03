<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Spec возраста.
 */
final class AgeSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param array $ages Ступени.
     *
     * @return void
     */
    public function __construct(
        private readonly array $ages,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'age';
    }

    /**
     * Ступени.
     *
     * @return array Значение.
     */
    public function getAges(): array
    {
        return $this->ages;
    }
}
