<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Геттеры пункта среза для C4, не порты DI.

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

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
     * Контракт spec, если документ лёг в тип.
     *
     * @return RuleSpec|null DTO или null.
     */
    public function getSpec(): ?RuleSpec
    {
        return $this->ruleVersionRecord->getSpec();
    }

    /**
     * Форма spec известного типа не разобралась.
     *
     * @return bool true, если версия битая.
     */
    public function isSpecBroken(): bool
    {
        return $this->ruleVersionRecord->isSpecBroken();
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
     * Список механик среза.
     *
     * @return array<int, array<string, mixed>> Строки mechanic_id и mechanic_payload.
     */
    public function getMechanics(): array
    {
        return $this->ruleVersionRecord->getMechanics();
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
