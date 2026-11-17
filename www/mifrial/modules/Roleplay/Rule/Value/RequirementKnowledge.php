<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Value;

/**
 * Уровни знаний листа по типам знания.
 */
final class RequirementKnowledge
{
    /**
     * Создаёт факты знаний.
     *
     * @param array<string, int> $fieldLevels Тип знания → максимальный уровень.
     *
     * @return void
     */
    public function __construct(public readonly array $fieldLevels = [])
    {
    }
}
