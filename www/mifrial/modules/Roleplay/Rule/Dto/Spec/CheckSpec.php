<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Spec проверки.
 */
final class CheckSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param ?string $parentCheckCode Родитель.
     * @param ?string $characteristicCode Характеристика.
     * @param bool $allowCharacteristicOverride Можно сменить характеристику.
     * @param ?int $defaultEfficiency Эффективность по умолчанию.
     * @param CheckDifficulty $difficulty Сложность.
     * @param string $allowedModes Режимы.
     * @param bool $dialogLaunch Запуск из диалога.
     * @param bool $ordinaryRoot Корень обычных проверок.
     * @param bool $concentrationToken Жетон концентрации.
     * @param bool $willpower Проверка воли.
     * @param bool $unstableCheck Проверка неустойчивости.
     * @param bool $hitCheck Проверка удара.
     * @param ?array $attachedRuleCodes Коды правил на броске.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $parentCheckCode,
        private readonly ?string $characteristicCode,
        private readonly bool $allowCharacteristicOverride,
        private readonly ?int $defaultEfficiency,
        private readonly CheckDifficulty $difficulty,
        private readonly string $allowedModes,
        private readonly bool $dialogLaunch,
        private readonly bool $ordinaryRoot,
        private readonly bool $concentrationToken,
        private readonly bool $willpower,
        private readonly bool $unstableCheck,
        private readonly bool $hitCheck,
        private readonly ?array $attachedRuleCodes,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'check';
    }

    /**
     * Родитель.
     *
     * @return ?string Значение.
     */
    public function getParentCheckCode(): ?string
    {
        return $this->parentCheckCode;
    }
    /**
     * Характеристика.
     *
     * @return ?string Значение.
     */
    public function getCharacteristicCode(): ?string
    {
        return $this->characteristicCode;
    }
    /**
     * Можно сменить характеристику.
     *
     * @return bool Значение.
     */
    public function isAllowCharacteristicOverride(): bool
    {
        return $this->allowCharacteristicOverride;
    }
    /**
     * Эффективность по умолчанию.
     *
     * @return ?int Значение.
     */
    public function getDefaultEfficiency(): ?int
    {
        return $this->defaultEfficiency;
    }
    /**
     * Сложность.
     *
     * @return CheckDifficulty Значение.
     */
    public function getDifficulty(): CheckDifficulty
    {
        return $this->difficulty;
    }
    /**
     * Режимы.
     *
     * @return string Значение.
     */
    public function getAllowedModes(): string
    {
        return $this->allowedModes;
    }
    /**
     * Запуск из диалога.
     *
     * @return bool Значение.
     */
    public function isDialogLaunch(): bool
    {
        return $this->dialogLaunch;
    }
    /**
     * Корень обычных проверок.
     *
     * @return bool Значение.
     */
    public function isOrdinaryRoot(): bool
    {
        return $this->ordinaryRoot;
    }
    /**
     * Жетон концентрации.
     *
     * @return bool Значение.
     */
    public function isConcentrationToken(): bool
    {
        return $this->concentrationToken;
    }
    /**
     * Проверка воли.
     *
     * @return bool Значение.
     */
    public function isWillpower(): bool
    {
        return $this->willpower;
    }
    /**
     * Проверка неустойчивости.
     *
     * @return bool Значение.
     */
    public function isUnstableCheck(): bool
    {
        return $this->unstableCheck;
    }
    /**
     * Проверка удара.
     *
     * @return bool Значение.
     */
    public function isHitCheck(): bool
    {
        return $this->hitCheck;
    }
    /**
     * Коды правил на броске.
     *
     * @return ?array Значение.
     */
    public function getAttachedRuleCodes(): ?array
    {
        return $this->attachedRuleCodes;
    }
}
