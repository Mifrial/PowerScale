<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Тело заклинания.
 */
final class SpellSpec
{
    /**
     * Создаёт тело.
     *
     * @param SpellValue $power Мощь.
     * @param SpellValue $control Контроль.
     * @param SpellDuration $duration Длительность.
     * @param string|null $targeting Выбор цели.
     * @param SpellDamage|null $damage Урон.
     * @param SpellCharge|null $charge Заряд.
     *
     * @return void
     */
    public function __construct(
        private readonly SpellValue $power,
        private readonly SpellValue $control,
        private readonly SpellDuration $duration,
        private readonly ?string $targeting,
        private readonly ?SpellDamage $damage,
        private readonly ?SpellCharge $charge,
    ) {
    }

    /**
     * Мощь.
     *
     * @return SpellValue Значение.
     */
    public function getPower(): SpellValue
    {
        return $this->power;
    }

    /**
     * Контроль.
     *
     * @return SpellValue Значение.
     */
    public function getControl(): SpellValue
    {
        return $this->control;
    }

    /**
     * Длительность.
     *
     * @return SpellDuration Длительность.
     */
    public function getDuration(): SpellDuration
    {
        return $this->duration;
    }

    /**
     * Вид длительности.
     *
     * @return string Код.
     */
    public function getDurationType(): string
    {
        return $this->duration->getType();
    }

    /**
     * Выбор цели.
     *
     * @return string|null Код или null.
     */
    public function getTargeting(): ?string
    {
        return $this->targeting;
    }

    /**
     * Урон.
     *
     * @return SpellDamage|null Урон или null.
     */
    public function getDamage(): ?SpellDamage
    {
        return $this->damage;
    }

    /**
     * Заряд.
     *
     * @return SpellCharge|null Заряд или null.
     */
    public function getCharge(): ?SpellCharge
    {
        return $this->charge;
    }
}
