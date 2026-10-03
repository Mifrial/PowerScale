<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Тело процесса.
 */
final class ProcessSpec
{
    /**
     * Создаёт тело.
     *
     * @param array<int, ProcessStep> $steps Шаги.
     * @param string $startStepCode Стартовый шаг.
     * @param array<int, string> $exitStepCodes Выходы.
     * @param \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition $transition Переход.
     * @param string|null $failure Провал.
     * @param array<int, \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect> $completionEffects Эффекты завершения.
     * @param bool $repeatWeaponCircumstance Помеха повтора.
     *
     * @return void
     */
    public function __construct(
        private readonly array $steps,
        private readonly string $startStepCode,
        private readonly array $exitStepCodes,
        private readonly \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition $transition,
        private readonly ?string $failure,
        private readonly array $completionEffects,
        private readonly bool $repeatWeaponCircumstance,
    ) {
    }

    /**
     * Шаги.
     *
     * @return array<int, ProcessStep> Шаги.
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Стартовый шаг.
     *
     * @return string Код.
     */
    public function getStartStepCode(): string
    {
        return $this->startStepCode;
    }

    /**
     * Выходы.
     *
     * @return array<int, string> Коды.
     */
    public function getExitStepCodes(): array
    {
        return $this->exitStepCodes;
    }

    /**
     * Переход.
     *
     * @return \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition Узел.
     */
    public function getTransition(): \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition
    {
        return $this->transition;
    }

    /**
     * Режим перехода.
     *
     * @return string Код.
     */
    public function getTransitionMode(): string
    {
        return $this->transition->getMode();
    }

    /**
     * Провал.
     *
     * @return string|null Код или null.
     */
    public function getFailure(): ?string
    {
        return $this->failure;
    }

    /**
     * Эффекты завершения.
     *
     * @return array<int, \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect> Список.
     */
    public function getCompletionEffects(): array
    {
        return $this->completionEffects;
    }

    /**
     * Помеха повтора оружия.
     *
     * @return bool true, если включена.
     */
    public function isRepeatWeaponCircumstance(): bool
    {
        return $this->repeatWeaponCircumstance;
    }
}
