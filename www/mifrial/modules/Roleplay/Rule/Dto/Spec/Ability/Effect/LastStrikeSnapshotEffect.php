<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка last_strike_snapshot.
 */
final class LastStrikeSnapshotEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $kind Вид.
     * @param array $hits Удары.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
        private readonly array $hits,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'last_strike_snapshot';
    }

    /**
     * Вид.
     *
     * @return string Значение.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Удары.
     *
     * @return array Значение.
     */
    public function getHits(): array
    {
        return $this->hits;
    }
}
