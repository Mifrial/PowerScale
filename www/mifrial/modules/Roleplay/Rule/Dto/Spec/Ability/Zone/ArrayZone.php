<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Zone;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityZone;

/**
 * Цена списком по уровням.
 */
final class ArrayZone implements AbilityZone
{
    /**
     * Создаёт цену.
     *
     * @param array<int, int> $levelsCost Цены уровней.
     *
     * @return void
     */
    public function __construct(private readonly array $levelsCost)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'array';
    }

    /**
     * Цены уровней.
     *
     * @return array<int, int> Список.
     */
    public function getLevelsCost(): array
    {
        return $this->levelsCost;
    }
}
