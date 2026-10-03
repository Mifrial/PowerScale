<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Итог разбора spec одной версии: документ либо лёг в контракт, либо нет.
 */
final class RuleSpecRead
{
    /**
     * Создаёт итог.
     *
     * @param RuleSpec|null $spec Контракт или null.
     * @param bool $broken true, если форма известного типа чужая.
     *
     * @return void
     * @param mixed $spec Поле.
     * @param mixed $broken Поле.
     */
    public function __construct(
        private readonly ?RuleSpec $spec,
        private readonly bool $broken,
    ) {
    }

    /**
     * Разобранный контракт.
     *
     * @return RuleSpec|null DTO или null.
     */
    public function getSpec(): ?RuleSpec
    {
        return $this->spec;
    }

    /**
     * Разбор известного типа не удался.
     *
     * @return bool true, если форма чужая.
     */
    public function isBroken(): bool
    {
        return $this->broken;
    }
}
