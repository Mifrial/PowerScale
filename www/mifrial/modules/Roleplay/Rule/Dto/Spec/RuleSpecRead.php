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
     * @param string $shapeKey Ключ формы при отказе.
     *
     * @return void
     */
    public function __construct(
        private readonly ?RuleSpec $spec,
        private readonly bool $broken,
        private readonly string $shapeKey = '',
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

    /**
     * Ключ формы, если разбор отказал.
     *
     * @return string Ключ или пусто.
     */
    public function getShapeKey(): string
    {
        return $this->shapeKey;
    }
}
