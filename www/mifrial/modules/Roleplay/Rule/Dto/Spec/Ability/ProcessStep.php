<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation\ProcessOperation;

/**
 * Шаг процесса.
 */
final class ProcessStep
{
    /**
     * Создаёт шаг.
     *
     * @param string $code Код.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param array<int, StepCost> $costs Цены.
     * @param string $interruptionMode Режим normal или emergency.
     * @param array<int, ActionEffect> $interruptionEffects Эффекты прерывания.
     * @param array<int, ProcessOperation> $operations Операции.
     * @param ProcessExecution $execution Режим исполнения.
     * @param StrikeModifier|null $strike Пакет удара шага.
     * @param array<int, CounterThreshold> $requiresCounters Пороги шага.
     * @param array<int, CounterOp> $counterOnSuccess Счётчики на успехе.
     * @param array<int, CounterOp> $counterOnFailure Счётчики на провале.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $description,
        private readonly array $costs,
        private readonly string $interruptionMode,
        private readonly array $interruptionEffects,
        private readonly array $operations,
        private readonly ProcessExecution $execution,
        private readonly ?StrikeModifier $strike = null,
        private readonly array $requiresCounters = [],
        private readonly array $counterOnSuccess = [],
        private readonly array $counterOnFailure = [],
    ) {
    }

    /**
     * Код.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Имя.
     *
     * @return string Текст.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Цены.
     *
     * @return array<int, StepCost> Список.
     */
    public function getCosts(): array
    {
        return $this->costs;
    }

    /**
     * Режим прерывания.
     *
     * @return string Код.
     */
    public function getInterruptionMode(): string
    {
        return $this->interruptionMode;
    }

    /**
     * Эффекты прерывания.
     *
     * @return array<int, ActionEffect> Список.
     */
    public function getInterruptionEffects(): array
    {
        return $this->interruptionEffects;
    }

    /**
     * Операции.
     *
     * @return array<int, ProcessOperation> Список.
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Режим исполнения.
     *
     * @return ProcessExecution Режим.
     */
    public function getExecution(): ProcessExecution
    {
        return $this->execution;
    }

    /**
     * Пакет удара шага.
     *
     * @return StrikeModifier|null Пакет или null.
     */
    public function getStrike(): ?StrikeModifier
    {
        return $this->strike;
    }

    /**
     * Пороги счётчиков шага.
     *
     * @return array<int, CounterThreshold> Пороги.
     */
    public function getRequiresCounters(): array
    {
        return $this->requiresCounters;
    }

    /**
     * Счётчики на успехе шага.
     *
     * @return array<int, CounterOp> Операции.
     */
    public function getCounterOnSuccess(): array
    {
        return $this->counterOnSuccess;
    }

    /**
     * Счётчики на провале шага.
     *
     * @return array<int, CounterOp> Операции.
     */
    public function getCounterOnFailure(): array
    {
        return $this->counterOnFailure;
    }
}
