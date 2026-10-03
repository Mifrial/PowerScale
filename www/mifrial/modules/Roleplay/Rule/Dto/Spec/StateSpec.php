<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Spec состояния.
 */
final class StateSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param ?string $iconCode Иконка.
     * @param string $valueType Тип значения.
     * @param string $aggregation Агрегация.
     * @param array $actionCodes Коды действий.
     * @param ?string $checkCode Проверка.
     * @param bool $damageRemainder Остаток повреждений.
     * @param bool $damageExhaustion Истощение от повреждений.
     * @param bool $bloodLoss Кровопотеря.
     * @param bool $declineWeakness Упадок, слабость.
     * @param bool $declineDisabled Упадок, бессилие.
     * @param bool $declineUnconscious Упадок, бессознательность.
     * @param bool $maim Увечье.
     * @param bool $burning Горение.
     * @param bool $poisoning Отравление.
     * @param bool $lying Лежачее положение.
     * @param bool $unstable Неустойчивость.
     * @param bool $magicDeviation Отклонение магии.
     * @param array<int, \Mifrial\Roleplay\Rule\Dto\Spec\State\StateEffect> $effects Эффекты.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $iconCode,
        private readonly string $valueType,
        private readonly string $aggregation,
        private readonly array $actionCodes,
        private readonly ?string $checkCode,
        private readonly bool $damageRemainder,
        private readonly bool $damageExhaustion,
        private readonly bool $bloodLoss,
        private readonly bool $declineWeakness,
        private readonly bool $declineDisabled,
        private readonly bool $declineUnconscious,
        private readonly bool $maim,
        private readonly bool $burning,
        private readonly bool $poisoning,
        private readonly bool $lying,
        private readonly bool $unstable,
        private readonly bool $magicDeviation,
        private readonly array $effects,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'state';
    }

    /**
     * Иконка.
     *
     * @return ?string Значение.
     */
    public function getIconCode(): ?string
    {
        return $this->iconCode;
    }
    /**
     * Тип значения.
     *
     * @return string Значение.
     */
    public function getValueType(): string
    {
        return $this->valueType;
    }
    /**
     * Агрегация.
     *
     * @return string Значение.
     */
    public function getAggregation(): string
    {
        return $this->aggregation;
    }
    /**
     * Коды действий.
     *
     * @return array Значение.
     */
    public function getActionCodes(): array
    {
        return $this->actionCodes;
    }
    /**
     * Проверка.
     *
     * @return ?string Значение.
     */
    public function getCheckCode(): ?string
    {
        return $this->checkCode;
    }
    /**
     * Остаток повреждений.
     *
     * @return bool Значение.
     */
    public function isDamageRemainder(): bool
    {
        return $this->damageRemainder;
    }
    /**
     * Истощение от повреждений.
     *
     * @return bool Значение.
     */
    public function isDamageExhaustion(): bool
    {
        return $this->damageExhaustion;
    }
    /**
     * Кровопотеря.
     *
     * @return bool Значение.
     */
    public function isBloodLoss(): bool
    {
        return $this->bloodLoss;
    }
    /**
     * Упадок, слабость.
     *
     * @return bool Значение.
     */
    public function isDeclineWeakness(): bool
    {
        return $this->declineWeakness;
    }
    /**
     * Упадок, бессилие.
     *
     * @return bool Значение.
     */
    public function isDeclineDisabled(): bool
    {
        return $this->declineDisabled;
    }
    /**
     * Упадок, бессознательность.
     *
     * @return bool Значение.
     */
    public function isDeclineUnconscious(): bool
    {
        return $this->declineUnconscious;
    }
    /**
     * Увечье.
     *
     * @return bool Значение.
     */
    public function isMaim(): bool
    {
        return $this->maim;
    }
    /**
     * Горение.
     *
     * @return bool Значение.
     */
    public function isBurning(): bool
    {
        return $this->burning;
    }
    /**
     * Отравление.
     *
     * @return bool Значение.
     */
    public function isPoisoning(): bool
    {
        return $this->poisoning;
    }
    /**
     * Лежачее положение.
     *
     * @return bool Значение.
     */
    public function isLying(): bool
    {
        return $this->lying;
    }
    /**
     * Неустойчивость.
     *
     * @return bool Значение.
     */
    public function isUnstable(): bool
    {
        return $this->unstable;
    }
    /**
     * Отклонение магии.
     *
     * @return bool Значение.
     */
    public function isMagicDeviation(): bool
    {
        return $this->magicDeviation;
    }

    /**
     * Эффекты.
     *
     * @return array<int, \Mifrial\Roleplay\Rule\Dto\Spec\State\StateEffect> Список.
     */
    public function getEffects(): array
    {
        return $this->effects;
    }
}
