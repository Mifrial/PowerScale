<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Operation;

/**
 * Ветка posture.
 */
final class PostureOperation implements ProcessOperation
{
    /**
     * Создаёт ветку.
     *
     * @param string $posture Стойка.
     *
     * @return void
     */
    public function __construct(
        private readonly string $posture,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'posture';
    }

    /**
     * Стойка.
     *
     * @return string Значение.
     */
    public function getPosture(): string
    {
        return $this->posture;
    }
}
