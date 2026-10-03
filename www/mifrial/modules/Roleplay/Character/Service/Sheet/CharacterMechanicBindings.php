<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\PurchaseSurchargePayload;

/**
 * Строки механик среза в binding доплаты. Чужая форма пропускается.
 */
final class CharacterMechanicBindings
{
    /**
     * Binding live-правил в порядке состава.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<int, MechanicBinding> Срезы для движка.
     */
    public function fromSlice(CharacterRuleSlice $slice): array
    {
        $bindings = [];
        foreach ($slice->getLiveRules() as $rule) {
            foreach ($rule->getMechanics() as $row) {
                $binding = is_array($row) ? $this->bindingOf($rule->getCode(), $row) : null;
                if ($binding !== null) {
                    $bindings[] = $binding;
                }
            }
        }

        return $bindings;
    }

    /**
     * Одна строка или null, если это не purchase_surcharge.
     *
     * @param string $ruleCode Код правила.
     * @param array<string, mixed> $row Строка mechanic_id и mechanic_payload.
     *
     * @return MechanicBinding|null Binding или null.
     */
    private function bindingOf(string $ruleCode, array $row): ?MechanicBinding
    {
        $mechanicId = $this->mechanicIdOf($row);
        $payload = $this->payloadOf($row);
        if ($mechanicId === null || $payload === null) {
            return null;
        }

        return new MechanicBinding($ruleCode, $mechanicId, $payload);
    }

    /**
     * Id каталога из строки.
     *
     * @param array<string, mixed> $row Строка механики.
     *
     * @return int|null Id или null.
     */
    private function mechanicIdOf(array $row): ?int
    {
        $mechanicId = $row['mechanic_id'] ?? null;
        if (!is_int($mechanicId) || $mechanicId < 1) {
            return null;
        }

        return $mechanicId;
    }

    /**
     * Payload доплаты или null, если форма чужая.
     *
     * @param array<string, mixed> $row Строка механики.
     *
     * @return PurchaseSurchargePayload|null Payload или null.
     */
    private function payloadOf(array $row): ?PurchaseSurchargePayload
    {
        $payload = $row['mechanic_payload'] ?? null;
        if (!is_array($payload) || !$this->isPurchaseSurcharge($payload)) {
            return null;
        }

        $filter = $payload['filter'];

        return new PurchaseSurchargePayload(
            $this->optionalString($filter, 'keyword_code'),
            $this->optionalString($filter, 'race_code'),
            $payload['free_count'],
            $payload['surcharge'],
        );
    }

    /**
     * Обязательные поля purchase_surcharge на месте.
     *
     * @param array<string, mixed> $payload JSON payload.
     *
     * @return bool true, если тип, filter и целые порог и доплата заданы.
     */
    private function isPurchaseSurcharge(array $payload): bool
    {
        return ($payload['type'] ?? null) === 'purchase_surcharge'
            && isset($payload['filter'])
            && is_array($payload['filter'])
            && isset($payload['free_count'], $payload['surcharge'])
            && is_int($payload['free_count'])
            && is_int($payload['surcharge']);
    }

    /**
     * Необязательная строка фильтра.
     *
     * @param array<string, mixed> $filter Объект filter.
     * @param string $field Имя поля.
     *
     * @return string|null Строка или null.
     */
    private function optionalString(array $filter, string $field): ?string
    {
        $value = $filter[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
