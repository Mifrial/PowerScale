<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Spec народности.
 */
final class EthnicitySpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param string $role Роль.
     * @param ?string $parentCode Родитель.
     * @param array $raceCodes Расы.
     * @param array $languageCodes Языки.
     * @param array $usages Пары языка.
     *
     * @return void
     */
    public function __construct(
        private readonly string $role,
        private readonly ?string $parentCode,
        private readonly array $raceCodes,
        private readonly array $languageCodes,
        private readonly array $usages,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'ethnicity';
    }

    /**
     * Роль.
     *
     * @return string Значение.
     */
    public function getRole(): string
    {
        return $this->role;
    }
    /**
     * Родитель.
     *
     * @return ?string Значение.
     */
    public function getParentCode(): ?string
    {
        return $this->parentCode;
    }
    /**
     * Расы.
     *
     * @return array Значение.
     */
    public function getRaceCodes(): array
    {
        return $this->raceCodes;
    }
    /**
     * Языки.
     *
     * @return array Значение.
     */
    public function getLanguageCodes(): array
    {
        return $this->languageCodes;
    }
    /**
     * Пары языка.
     *
     * @return array Значение.
     */
    public function getUsages(): array
    {
        return $this->usages;
    }
}
