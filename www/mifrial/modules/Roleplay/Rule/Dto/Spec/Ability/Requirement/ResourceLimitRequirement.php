<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Минимум лимита ресурса.
 */
final class ResourceLimitRequirement implements AbilityRequirement
{
    /**
     * Создаёт требование.
     *
     * @param string $resourceCode Ресурс.
     * @param int|DimensionalNumber|null $min Минимум.
     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int|DimensionalNumber|null $min,
    ) {
    }

    /**
     * Вид.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resource_limit';
    }

    /**
     * Ресурс.
     *
     * @return string Код.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Минимум.
     *
     * @return int|DimensionalNumber|null Значение или null.
     */
    public function getMin(): int|DimensionalNumber|null
    {
        return $this->min;
    }
}
