<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Transition\ProcessTransition;

/**
 * Тело процесса.
 */
final class ProcessSpec
{
    public const TARGET_ANY = 'any';

    public const TARGET_SINGLE = 'single';

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
     * @param array<int, ProcessCounter> $counters Счётчики сессии.
     * @param string $targetMode Режим цели: any или single.
     *
     * @return void
     */
    public function __construct(
        private readonly array $steps,
        private readonly string $startStepCode,
        private readonly array $exitStepCodes,
        private readonly ProcessTransition $transition,
        private readonly ?string $failure,
        private readonly array $completionEffects,
        private readonly bool $repeatWeaponCircumstance,
        private readonly array $counters = [],
        private readonly string $targetMode = self::TARGET_ANY,
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
    public function getTransition(): ProcessTransition
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

    /**
     * Счётчики сессии.
     *
     * @return array<int, ProcessCounter> Объявленные счётчики.
     */
    public function getCounters(): array
    {
        return $this->counters;
    }

    /**
     * Режим цели.
     *
     * @return string any или single.
     */
    public function getTargetMode(): string
    {
        return $this->targetMode;
    }

    /**
     * Держит ли процесс одну цель.
     *
     * @return bool true для single.
     */
    public function isSingleTarget(): bool
    {
        return $this->targetMode === self::TARGET_SINGLE;
    }
}
