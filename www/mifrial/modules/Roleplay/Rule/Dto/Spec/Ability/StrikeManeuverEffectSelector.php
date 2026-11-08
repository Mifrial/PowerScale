<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Селектор эффекта, который усиление манёвра заменяет.
 *
 * Значение намеренно не входит в селектор: замена ищет эффект по виду и
 * состоянию, поэтому усиление не ломается от смены базового значения.
 */
final class StrikeManeuverEffectSelector
{
    /**
     * Создаёт селектор.
     *
     * @param string $kind Вид эффекта.
     * @param string $state Код правила состояния.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
        private readonly string $state,
    ) {
    }

    /**
     * Вид эффекта.
     *
     * @return string Вид.
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
    public function getState(): string
    {
        return $this->state;
    }
}
