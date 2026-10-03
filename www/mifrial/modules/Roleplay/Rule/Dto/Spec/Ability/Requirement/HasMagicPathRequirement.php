<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Нужен магический путь.
 */
final class HasMagicPathRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $pathCode Путь.
     *
     * @return void
     */
    public function __construct(private readonly string $pathCode)
    {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'has_magic_path';
    }

    /**
     * Путь.
     *
     * @return string Код.
     */
    public function getPathCode(): string
    {
        return $this->pathCode;
    }
}
