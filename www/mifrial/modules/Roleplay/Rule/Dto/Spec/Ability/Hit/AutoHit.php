<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit;

/**
 * Автопопадание с заданным успехом.
 */
final class AutoHit implements HitResolution
{
    /**
     * Создаёт доставку.
     *
     * @param int $rating Успех.
     *
     * @return void
     */
    public function __construct(private readonly int $rating)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'auto';
    }

    /**
     * Успех.
     *
     * @return int Число.
     */
    public function getRating(): int
    {
        return $this->rating;
    }
}
