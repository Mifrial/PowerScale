<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка current_action_check_modifier.
 */
final class CurrentActionCheckModifierEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param array $checkCodes Проверки.
     * @param int $delta Сдвиг.
     * @param ?string $sourceCode Источник.
     *
     * @return void
     */
    public function __construct(
        private readonly array $checkCodes,
        private readonly int $delta,
        private readonly ?string $sourceCode,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'current_action_check_modifier';
    }

    /**
     * Проверки.
     *
     * @return array Значение.
     */
    public function getCheckCodes(): array
    {
        return $this->checkCodes;
    }

    /**
     * Сдвиг.
     *
     * @return int Значение.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Источник.
     *
     * @return ?string Значение.
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
