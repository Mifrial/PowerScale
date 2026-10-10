<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Параметры одного броска до событий механик.
 */
final class RollSpec
{
    /**
     * Нейтральная эффективность: её подменяет payload «Бросок».
     */
    private const NEUTRAL_EFFICIENCY = 3;

    /**
     * Нейтральный размер куба: его подменяет payload «Бросок».
     */
    private const NEUTRAL_DIE_SIZE = 0;

    /**
     * Собирает спеку.
     *
     * @param int $diceCount Число кубов до события пула.
     * @param int $dieFaces Число граней.
     * @param int $efficiency Порог успеха грани.
     * @param int $dieSize Размер успехов броска.
     * @param array<int, RollAdvantage> $advantages Преимущества и помехи.
     * @param bool $explicitEfficiency Признак явно заданной эффективности.
     *
     * @return void
     */
    public function __construct(
        private readonly int $diceCount,
        private readonly int $dieFaces,
        private readonly int $efficiency,
        private readonly int $dieSize,
        private readonly array $advantages = [],
        private readonly bool $explicitEfficiency = false,
    ) {
    }

    /**
     * Подставляет дефолты payload только в нейтральные точки.
     *
     * @param RollMechanicPayload $payload Payload правила «Бросок».
     *
     * @return self Спека после дефолтов.
     */
    public function withRollDefaults(RollMechanicPayload $payload): self
    {
        return new self(
            $this->diceCount,
            $this->dieFaces,
            $this->efficiencyFrom($payload),
            $this->dieSizeFrom($payload),
            $this->advantagesFrom($payload),
            $this->explicitEfficiency,
        );
    }

    /**
     * Число кубов.
     *
     * @return int Целое.
     */
    public function getDiceCount(): int
    {
        return $this->diceCount;
    }

    /**
     * Число граней.
     *
     * @return int Целое.
     */
    public function getDieFaces(): int
    {
        return $this->dieFaces;
    }

    /**
     * Порог успеха грани.
     *
     * @return int Целое.
     */
    public function getEfficiency(): int
    {
        return $this->efficiency;
    }

    /**
     * Проверяет явность эффективности.
     *
     * @return bool true, если эффективность задана источником.
     */
    public function isEfficiencyExplicit(): bool
    {
        return $this->explicitEfficiency;
    }

    /**
     * Размер успехов.
     *
     * @return int Целое.
     */
    public function getDieSize(): int
    {
        return $this->dieSize;
    }

    /**
     * Преимущества и помехи.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function getAdvantages(): array
    {
        return $this->advantages;
    }

    /**
     * Эффективность после нейтральной точки.
     *
     * @param RollMechanicPayload $payload Payload.
     *
     * @return int Эффективность.
     */
    private function efficiencyFrom(RollMechanicPayload $payload): int
    {
        if (
            $this->explicitEfficiency
            || $this->efficiency !== self::NEUTRAL_EFFICIENCY
            || $payload->getEfficiency() === null
        ) {
            return $this->efficiency;
        }

        return $payload->getEfficiency();
    }

    /**
     * Размер куба после нейтральной точки.
     *
     * @param RollMechanicPayload $payload Payload.
     *
     * @return int Размер.
     */
    private function dieSizeFrom(RollMechanicPayload $payload): int
    {
        if ($this->dieSize !== self::NEUTRAL_DIE_SIZE || $payload->getDieSize() === null) {
            return $this->dieSize;
        }

        return $payload->getDieSize();
    }

    /**
     * Преимущества после пустого списка.
     *
     * @param RollMechanicPayload $payload Payload.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    private function advantagesFrom(RollMechanicPayload $payload): array
    {
        $adv = $payload->getAdv();
        if ($this->advantages !== [] || $adv === null || $adv === 0) {
            return $this->advantages;
        }

        return [new RollAdvantage('roll', $adv)];
    }
}
