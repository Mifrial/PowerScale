<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Магическое отклонение: кубы, порог, состояние и взрыв при дубле.
 */
final class SpellDeviationSpec
{
    /**
     * Создаёт описание отклонения.
     *
     * @param int $diceCount Число кубов.
     * @param int $dieFaces Число граней.
     * @param int $neutralSum Нейтральная сумма.
     * @param string $stateRuleCode Код состояния на источнике.
     * @param SpellDeviationBurst|null $burst Взрыв при дубле или null.
     *
     * @return void
     */
    public function __construct(
        private readonly int $diceCount,
        private readonly int $dieFaces,
        private readonly int $neutralSum,
        private readonly string $stateRuleCode,
        private readonly ?SpellDeviationBurst $burst,
    ) {
    }

    /**
     * Число кубов.
     *
     * @return int Число.
     */
    public function getDiceCount(): int
    {
        return $this->diceCount;
    }

    /**
     * Число граней.
     *
     * @return int Число.
     */
    public function getDieFaces(): int
    {
        return $this->dieFaces;
    }

    /**
     * Нейтральная сумма: сила — сумма граней минус она.
     *
     * @return int Число.
     */
    public function getNeutralSum(): int
    {
        return $this->neutralSum;
    }

    /**
     * Код состояния на источнике.
     *
     * @return string Код.
     */
    public function getStateRuleCode(): string
    {
        return $this->stateRuleCode;
    }

    /**
     * Взрыв при дубле.
     *
     * @return SpellDeviationBurst|null Взрыв или null.
     */
    public function getBurst(): ?SpellDeviationBurst
    {
        return $this->burst;
    }
}
