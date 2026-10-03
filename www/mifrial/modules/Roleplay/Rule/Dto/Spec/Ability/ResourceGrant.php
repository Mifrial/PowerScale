<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Грант ресурса.
 */
final class ResourceGrant implements AbilityGrant
{
    /**
     * Создаёт грант.
     *
     * @param string $resourceCode Поле.
     * @param int|DimensionalNumber $limit Поле.
     * @param bool $permanent Поле.     *
     * @return void
     */
    public function __construct(
        private readonly string $resourceCode,
        private readonly int|DimensionalNumber $limit,
        private readonly bool $permanent,
    ) {
    }

    /**
     * Тип гранта.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'resource';
    }

    /**
     * Код.
     *
     * @return string Значение.
     */
    public function getResourceCode(): string
    {
        return $this->resourceCode;
    }

    /**
     * Лимит.
     *
     * @return int|DimensionalNumber Значение.
     */
    public function getLimit(): int|DimensionalNumber
    {
        return $this->limit;
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
