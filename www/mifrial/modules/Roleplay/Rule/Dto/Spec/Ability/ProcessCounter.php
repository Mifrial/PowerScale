<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Объявленный счётчик процесса и его стартовое значение.
 */
final class ProcessCounter
{
    /**
     * Создаёт счётчик.
     *
     * @param string $code Код.
     * @param int $initial Старт.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly int $initial = 0,
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
     * Стартовое значение.
     *
     * @return int Число.
     */
    public function getInitial(): int
    {
        return $this->initial;
    }
}
