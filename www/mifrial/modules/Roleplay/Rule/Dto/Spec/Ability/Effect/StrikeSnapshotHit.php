<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Удар снимка.
 */
final class StrikeSnapshotHit
{
    /**
     * Создаёт значение.
     *
     * @param string $targetKey Ключ цели.
     * @param int $attackSr Успех атаки.
     * @param ?string $reaction Реакция.
     * @param bool $damaged Было повреждение.
     *
     * @return void
     */
    public function __construct(
        private readonly string $targetKey,
        private readonly int $attackSr,
        private readonly ?string $reaction,
        private readonly bool $damaged,
    ) {
    }

    /**
     * Ключ цели.
     *
     * @return string Значение.
     */
    public function getTargetKey(): string
    {
        return $this->targetKey;
    }

    /**
     * Успех атаки.
     *
     * @return int Значение.
     */
    public function getAttackSr(): int
    {
        return $this->attackSr;
    }

    /**
     * Реакция.
     *
     * @return ?string Значение.
     */
    public function getReaction(): ?string
    {
        return $this->reaction;
    }

    /**
     * Было повреждение.
     *
     * @return bool Значение.
     */
    public function isDamaged(): bool
    {
        return $this->damaged;
    }
}
