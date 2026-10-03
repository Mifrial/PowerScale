<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Заряд заклинания.
 */
final class SpellCharge
{
    /**
     * Создаёт заряд.
     *
     * @param string $stateCode Состояние.
     * @param int $grant Выдача.
     * @param int $defaultCap Потолок.
     * @param int $actionPoints ОД траты.
     * @param int $amount Количество траты.
     * @param string $keywordCode Признак.
     * @param array<int, string> $excludedDurations Исключённые длительности.
     * @param string $creationMax Потолок создания.
     *
     * @return void
     */
    public function __construct(
        private readonly string $stateCode,
        private readonly int $grant,
        private readonly int $defaultCap,
        private readonly int $actionPoints,
        private readonly int $amount,
        private readonly string $keywordCode,
        private readonly array $excludedDurations,
        private readonly string $creationMax,
    ) {
    }

    /**
     * Состояние.
     *
     * @return string Код.
     */
    public function getStateCode(): string
    {
        return $this->stateCode;
    }

    /**
     * Выдача.
     *
     * @return int Число.
     */
    public function getGrant(): int
    {
        return $this->grant;
    }

    /**
     * Потолок.
     *
     * @return int Число.
     */
    public function getDefaultCap(): int
    {
        return $this->defaultCap;
    }

    /**
     * ОД траты.
     *
     * @return int Число.
     */
    public function getActionPoints(): int
    {
        return $this->actionPoints;
    }

    /**
     * Количество траты.
     *
     * @return int Число.
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Признак.
     *
     * @return string Код.
     */
    public function getKeywordCode(): string
    {
        return $this->keywordCode;
    }

    /**
     * Исключённые длительности.
     *
     * @return array<int, string> Коды.
     */
    public function getExcludedDurations(): array
    {
        return $this->excludedDurations;
    }

    /**
     * Потолок создания.
     *
     * @return string Код.
     */
    public function getCreationMax(): string
    {
        return $this->creationMax;
    }
}
