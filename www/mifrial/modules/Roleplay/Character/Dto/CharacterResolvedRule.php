<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Геттеры пункта среза для C4, не порты DI.

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;

/**
 * Живое правило среза: признаки уже кодами.
 */
final class CharacterResolvedRule
{
    /**
     * Создаёт обёртку.
     *
     * @param RuleVersionRecord $ruleVersionRecord Пункт среза.
     * @param array<int, string> $keywordCodes Коды признаков.
     *
     * @return void
     */
    public function __construct(
        private readonly RuleVersionRecord $ruleVersionRecord,
        private readonly array $keywordCodes,
    ) {
    }

    /**
     * Семантический ключ.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->ruleVersionRecord->getCode();
    }

    /**
     * Тип правила.
     *
     * @return string Код типа.
     */
    public function getType(): string
    {
        return $this->ruleVersionRecord->getType();
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->ruleVersionRecord->getName();
    }

    /**
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->ruleVersionRecord->getDescription();
    }

    /**
     * JSON spec как в срезе.
     *
     * @return array<string|int, mixed> Spec.
     */
    public function getSpec(): array
    {
        return $this->ruleVersionRecord->getSpec();
    }

    /**
     * Признаки среза кодами.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordCodes(): array
    {
        return $this->keywordCodes;
    }

    /**
     * Механика.
     *
     * @return int|null Id.
     */
    public function getMechanicId(): ?int
    {
        return $this->ruleVersionRecord->getMechanicId();
    }

    /**
     * Payload механики.
     *
     * @return array<string|int, mixed> JSON.
     */
    public function getMechanicPayload(): array
    {
        return $this->ruleVersionRecord->getMechanicPayload();
    }

    /**
     * Редакционный статус. На live не влияет.
     *
     * @return string Статус.
     */
    public function getContentStatus(): string
    {
        return $this->ruleVersionRecord->getContentStatus();
    }
}
