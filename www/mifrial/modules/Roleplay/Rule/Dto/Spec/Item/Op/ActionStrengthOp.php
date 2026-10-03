<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка action_strength.
 */
final class ActionStrengthOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param string $field Поле.
     * @param int $delta Сдвиг.
     * @param array $profiles Профили.
     * @param array $damageTypeCodes Типы урона.
     *
     * @return void
     */
    public function __construct(
        private readonly string $field,
        private readonly int $delta,
        private readonly array $profiles,
        private readonly array $damageTypeCodes,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'action_strength';
    }

    /**
     * Поле.
     *
     * @return string Значение.
     */
    public function getField(): string
    {
        return $this->field;
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
     * Профили.
     *
     * @return array Значение.
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Типы урона.
     *
     * @return array Значение.
     */
    public function getDamageTypeCodes(): array
    {
        return $this->damageTypeCodes;
    }
}
