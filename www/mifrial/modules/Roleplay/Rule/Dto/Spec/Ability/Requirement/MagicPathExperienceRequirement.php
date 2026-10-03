<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;

/**
 * Минимум опыта на пути.
 */
final class MagicPathExperienceRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $pathCode Путь.
     * @param int $min Минимум.
     *
     * @return void
     */
    public function __construct(
        private readonly string $pathCode,
        private readonly int $min,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'magic_path_experience';
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

    /**
     * Минимум.
     *
     * @return int Число.
     */
    public function getMin(): int
    {
        return $this->min;
    }
}
