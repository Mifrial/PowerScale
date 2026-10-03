<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Откуда берётся сложность проверки.
 */
final class CheckDifficulty
{
    /**
     * Создаёт сложность.
     *
     * @param string $kind ask, from_state или none.
     * @param string $stateCode Код состояния для from_state.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
        private readonly string $stateCode,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Код состояния.
     *
     * @return string Код.
     */
    public function getStateCode(): string
    {
        return $this->stateCode;
    }
}
