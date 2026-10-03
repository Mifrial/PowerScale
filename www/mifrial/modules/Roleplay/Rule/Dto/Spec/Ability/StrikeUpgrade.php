<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Модификатор запуска удара.
 */
final class StrikeUpgrade
{
    /**
     * Создаёт модификатор.
     *
     * @param string $exclusiveGroup Группа режимов.
     * @param array<int, StrikeUpgradeMode> $modes Режимы.
     * @param bool $requiresPhysiology Нужна физиология.
     *
     * @return void
     */
    public function __construct(
        private readonly string $exclusiveGroup,
        private readonly array $modes,
        private readonly bool $requiresPhysiology,
    ) {
    }

    /**
     * Группа.
     *
     * @return string Код.
     */
    public function getExclusiveGroup(): string
    {
        return $this->exclusiveGroup;
    }

    /**
     * Режимы.
     *
     * @return array<int, StrikeUpgradeMode> Список.
     */
    public function getModes(): array
    {
        return $this->modes;
    }

    /**
     * Нужна физиология.
     *
     * @return bool true, если нужна.
     */
    public function isRequiresPhysiology(): bool
    {
        return $this->requiresPhysiology;
    }
}
