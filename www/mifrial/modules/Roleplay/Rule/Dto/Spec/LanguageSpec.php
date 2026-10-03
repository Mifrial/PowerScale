<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Spec\SpecShape;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Spec правила language.
 */
final class LanguageSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param mixed $role Поле.
     * @param mixed $parentCode Поле.
     * @param mixed $scriptCodes Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $role,
        private readonly ?string $parentCode,
        private readonly array $scriptCodes,
    ) {
    }


    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'language';
    }

    /**
     * Роль.
     *
     * @return string Значение.
     */
    public function getRole(): string
    {
        return $this->role;
    }
    /**
     * Родитель.
     *
     * @return ?string Значение.
     */
    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }
    /**
     * Письменности.
     *
     * @return array Значение.
     */
    public function getScriptCodes(): array
    {
        return $this->scriptCodes;
    }

}
