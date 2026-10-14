<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Снимает потолок перевязки и помечает строку как aided.
 */
final class WoundBandageAidGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param bool $permanent Постоянный.
     *
     * @return void
     */
    public function __construct(private readonly bool $permanent)
    {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'wound_bandage_aid';
    }

    /**
     * Постоянный грант.
     *
     * @return bool Значение.
     */
    public function isPermanent(): bool
    {
        return $this->permanent;
    }
}
