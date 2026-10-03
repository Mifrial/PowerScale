<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Save;

use Mifrial\Roleplay\Character\Dto\Action\CreateCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\ValidateCharacterInput;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterCharacteristicPurchase;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterCustomRule;
use Mifrial\Roleplay\Character\Dto\CharacterInventoryChoice;

/**
 * Собирает CharacterChoices из уже проверенного входа. Лишние поля выбора остаются в JSON choices.
 */
final class CharacterChoiceAssembler
{
    /**
     * Документ choices из полей входа. Наличные дописывает сборка.
     *
     * @param CreateCharacterInput|UpdateCharacterInput|ValidateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Choices.
     */
    public function buildDocument(CreateCharacterInput|UpdateCharacterInput|ValidateCharacterInput $input): array
    {
        return [
            'name' => $input->name,
            'shortDescription' => $input->shortDescription,
            'fullDescription' => $input->fullDescription,
            'ageYears' => $input->ageYears,
            'limits' => $input->limits,
            'raceCode' => $input->raceCode,
            'characteristicPurchases' => $input->characteristicPurchases,
            'abilities' => $input->abilities,
            'inventory' => $input->inventory,
            'customRules' => $input->customRules,
            'active' => $input->active ?? true,
        ];
    }

    /**
     * Выборы для валидатора.
     *
     * @param string $name Имя.
     * @param string $raceCode Код расы.
     * @param array<mixed> $abilities Способности.
     * @param array<mixed> $inventory Предметы.
     * @param array<mixed> $characteristicPurchases Закупки.
     * @param array<mixed> $customRules Свои правила.
     * @param bool|null $active Признак или null, если ключа не было.
     *
     * @return CharacterChoices Выборы.
     */
    public function assemble(
        string $name,
        string $raceCode,
        array $abilities,
        array $inventory,
        array $characteristicPurchases,
        array $customRules,
        ?bool $active,
    ): CharacterChoices {
        return new CharacterChoices(
            $name,
            $raceCode,
            $this->abilities($abilities),
            $this->inventory($inventory),
            $this->purchases($characteristicPurchases),
            $this->customRules($customRules),
            $active,
        );
    }

    /**
     * Способности с кодом и уровнем.
     *
     * @param array<mixed> $abilities Сырой список.
     *
     * @return array<int, CharacterAbilityChoice> Выборы.
     */
    private function abilities(array $abilities): array
    {
        $choices = [];
        foreach ($abilities as $ability) {
            if (is_array($ability) && is_string($ability['ruleCode'] ?? null) && is_int($ability['level'] ?? null)) {
                $choices[] = $this->ability($ability);
            }
        }

        return $choices;
    }

    /**
     * Одна способность.
     *
     * @param array<string, mixed> $ability Объект.
     *
     * @return CharacterAbilityChoice Выбор.
     */
    private function ability(array $ability): CharacterAbilityChoice
    {
        $grant = is_array($ability['grantedBy'] ?? null) ? $ability['grantedBy'] : [];

        return new CharacterAbilityChoice(
            $ability['ruleCode'],
            $ability['level'],
            is_string($ability['domain'] ?? null) ? $ability['domain'] : '',
            is_string($ability['domainCode'] ?? null) ? $ability['domainCode'] : '',
            is_string($grant['ruleCode'] ?? null) ? $grant['ruleCode'] : null,
            is_string($ability['studyPairId'] ?? null) ? $ability['studyPairId'] : null,
            is_string($ability['studyPairRole'] ?? null) ? $ability['studyPairRole'] : null,
        );
    }

    /**
     * Предметы с кодом правила. Кастом без ruleCode валидатор ссылок не видит.
     *
     * @param array<mixed> $inventory Сырой список.
     *
     * @return array<int, CharacterInventoryChoice> Выборы.
     */
    private function inventory(array $inventory): array
    {
        $choices = [];
        foreach ($inventory as $item) {
            if (is_array($item) && is_string($item['ruleCode'] ?? null)) {
                $choices[] = new CharacterInventoryChoice(
                    $item['ruleCode'],
                    ($item['equipped'] ?? false) === true,
                    is_int($item['quantity'] ?? null) ? $item['quantity'] : 1,
                );
            }
        }

        return $choices;
    }

    /**
     * Закупки с кодом и ценой.
     *
     * @param array<mixed> $purchases Сырой список.
     *
     * @return array<int, CharacterCharacteristicPurchase> Закупки.
     */
    private function purchases(array $purchases): array
    {
        $choices = [];
        foreach ($purchases as $purchase) {
            $code = is_array($purchase) ? ($purchase['characteristicCode'] ?? null) : null;
            $cost = is_array($purchase) ? ($purchase['cost'] ?? null) : null;
            if (is_string($code) && is_int($cost)) {
                $choices[] = new CharacterCharacteristicPurchase($code, $cost);
            }
        }

        return $choices;
    }

    /**
     * Свои правила. Чужое поле становится пустой строкой и не проходит проверку.
     *
     * @param array<mixed> $customRules Сырой список.
     *
     * @return array<int, CharacterCustomRule> Записи.
     */
    private function customRules(array $customRules): array
    {
        $rules = [];
        foreach ($customRules as $rule) {
            if (is_array($rule)) {
                $rules[] = $this->customRule($rule);
            }
        }

        return $rules;
    }

    /**
     * Одна запись. Нет строки — пусто.
     *
     * @param array<string, mixed> $rule Объект.
     *
     * @return CharacterCustomRule Запись.
     */
    private function customRule(array $rule): CharacterCustomRule
    {
        $replaced = $rule['replacedWithRuleCode'] ?? null;

        return new CharacterCustomRule(
            is_int($rule['id'] ?? null) ? $rule['id'] : null,
            is_string($rule['kind'] ?? null) ? $rule['kind'] : '',
            is_string($rule['name'] ?? null) ? $rule['name'] : '',
            is_string($rule['description'] ?? null) ? $rule['description'] : '',
            is_string($rule['status'] ?? null) ? $rule['status'] : '',
            is_string($replaced) ? $replaced : null,
        );
    }
}
