<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Один типизированный эффект манёвра.
 *
 * Эффект описывает смысл, а не правило: для состояния берётся код правила
 * состояния из контента и значение прироста. Кодов карточек здесь нет.
 */
final class StrikeManeuverEffect
{
    /**
     * Эффект добавляет состояние цели.
     */
    public const KIND_STATE = 'state';

    /**
     * Создаёт эффект.
     *
     * @param string $kind Вид эффекта.
     * @param string $state Код правила состояния.
     * @param int $amount Значение прироста.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
        private readonly string $state,
        private readonly int $amount,
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

    /**
     * Значение прироста.
     *
     * @return int Значение.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Эффект совпадает с селектором подмены.
     *
     * @param StrikeManeuverEffectSelector $selector Селектор.
     *
     * @return bool Истина, если вид и состояние совпали.
     */
    public function matches(StrikeManeuverEffectSelector $selector): bool
    {
        return $this->kind === $selector->getKind() && $this->state === $selector->getState();
    }
}
