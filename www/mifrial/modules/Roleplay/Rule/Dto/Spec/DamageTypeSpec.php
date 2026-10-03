<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Spec типа урона.
 */
final class DamageTypeSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param string $genitive Родительный.
     * @param string $dative Дательный.
     * @param bool $defenseIgnored Линии защиты не складываются.
     * @param bool $modifiesSpellDifficulty Меняет сложность сотворения.
     * @param ?int $maxSuccessRating Потолок РУ.
     *
     * @return void
     */
    public function __construct(
        private readonly string $genitive,
        private readonly string $dative,
        private readonly bool $defenseIgnored,
        private readonly bool $modifiesSpellDifficulty,
        private readonly ?int $maxSuccessRating,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'damage_type';
    }

    /**
     * Родительный.
     *
     * @return string Значение.
     */
    public function getGenitive(): string
    {
        return $this->genitive;
    }
    /**
     * Дательный.
     *
     * @return string Значение.
     */
    public function getDative(): string
    {
        return $this->dative;
    }
    /**
     * Линии защиты не складываются.
     *
     * @return bool Значение.
     */
    public function isDefenseIgnored(): bool
    {
        return $this->defenseIgnored;
    }
    /**
     * Меняет сложность сотворения.
     *
     * @return bool Значение.
     */
    public function isModifiesSpellDifficulty(): bool
    {
        return $this->modifiesSpellDifficulty;
    }
    /**
     * Потолок РУ.
     *
     * @return ?int Значение.
     */
    public function getMaxSuccessRating(): ?int
    {
        return $this->maxSuccessRating;
    }
}
