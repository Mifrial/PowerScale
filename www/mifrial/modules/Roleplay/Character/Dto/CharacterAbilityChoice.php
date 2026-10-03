<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля выбора способности, не порты.

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Одна выбранная способность: уровень, домен, грант и пара пути.
 */
final class CharacterAbilityChoice
{
    /**
     * Создаёт выбор.
     *
     * @param string $ruleCode Код способности.
     * @param int $level Уровень.
     * @param string $domain Домен экземпляра.
     * @param string $domainCode Код пути или домена.
     * @param string|null $grantedByRuleCode Код донора или null.
     * @param string|null $studyPairId Id пары или null.
     * @param string|null $studyPairRole charged, free или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly int $level,
        private readonly string $domain,
        private readonly string $domainCode,
        private readonly ?string $grantedByRuleCode,
        private readonly ?string $studyPairId,
        private readonly ?string $studyPairRole,
    ) {
    }

    /**
     * Код способности.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Уровень выбора.
     *
     * @return int Уровень.
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Домен экземпляра.
     *
     * @return string Домен.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Код пути.
     *
     * @return string Код или пустая строка.
     */
    public function getDomainCode(): string
    {
        return $this->domainCode;
    }

    /**
     * Донор гранта.
     *
     * @return string|null Код правила или null.
     */
    public function getGrantedByRuleCode(): ?string
    {
        return $this->grantedByRuleCode;
    }

    /**
     * Id пары пути.
     *
     * @return string|null Id или null.
     */
    public function getStudyPairId(): ?string
    {
        return $this->studyPairId;
    }

    /**
     * Роль в паре.
     *
     * @return string|null Роль или null.
     */
    public function getStudyPairRole(): ?string
    {
        return $this->studyPairRole;
    }

    /**
     * Ключ экземпляра ruleCode:domainCode:domain.
     *
     * @return string Ключ.
     */
    public function instanceKey(): string
    {
        return $this->ruleCode . ':' . $this->domainCode . ':' . $this->domain;
    }
}
