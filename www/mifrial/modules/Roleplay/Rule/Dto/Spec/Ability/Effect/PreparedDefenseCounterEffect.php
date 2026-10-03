<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect;

/**
 * Ветка prepared_defense_counter.
 */
final class PreparedDefenseCounterEffect implements ActionEffect
{
    /**
     * Создаёт ветку.
     *
     * @param string $targetKey Ключ цели.
     * @param string $reaction Реакция.
     *
     * @return void
     */
    public function __construct(
        private readonly string $targetKey,
        private readonly string $reaction,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'prepared_defense_counter';
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
     * Реакция.
     *
     * @return string Значение.
     */
    public function getReaction(): string
    {
        return $this->reaction;
    }
}
