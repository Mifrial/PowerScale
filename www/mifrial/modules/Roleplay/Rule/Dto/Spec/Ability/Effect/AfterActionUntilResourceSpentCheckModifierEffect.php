<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка after_action_until_resource_spent_check_modifier.
 */
final class AfterActionUntilResourceSpentCheckModifierEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $resourceCode Ресурс.
     * @param int $amount Количество.
     * @param array $checkCodes Проверки.
     * @param int $delta Сдвиг.
     * @param ?string $sourceCode Источник.
     * @param ?string $appliesTo Область применения.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int $amount,
        private readonly array $checkCodes,
        private readonly int $delta,
        private readonly ?string $sourceCode,
        private readonly ?string $appliesTo,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'after_action_until_resource_spent_check_modifier';
    }

    /**
     * Ресурс.
     *
     * @return string Значение.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Количество.
     *
     * @return int Значение.
     */
    public function getAmount(): int
    {
        return $this->amount;
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

    /**
     * Область применения.
     *
     * @return ?string Значение.
     */
    public function getAppliesTo(): ?string
    {
        return $this->appliesTo;
    }
}
