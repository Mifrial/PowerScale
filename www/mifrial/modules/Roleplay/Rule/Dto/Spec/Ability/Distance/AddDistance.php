<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Distance;

/**
 * Ветка add.
 */
final class AddDistance implements ProcessDistance
{
    /**
     * Создаёт ветку.
     *
     * @param array $parts Слагаемые.
     *
     * @return void
     */
    public function __construct(
        private readonly array $parts,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'add';
    }

    /**
     * Слагаемые.
     *
     * @return array Значение.
     */
    public function getParts(): array
    {
        return $this->parts;
    }
}
