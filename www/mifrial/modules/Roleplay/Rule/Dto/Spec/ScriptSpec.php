<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Spec\SpecShape;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Spec правила script.
 */
final class ScriptSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param mixed $kind Поле.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
    ) {
    }


    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'script';
    }

    /**
     * Вид письменности.
     *
     * @return string Значение.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

}
