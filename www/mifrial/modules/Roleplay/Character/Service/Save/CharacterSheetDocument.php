<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Save;

use Mifrial\Roleplay\Character\Dto\CharacterEquippedModifier;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;

/**
 * JSON снимка, который пишется в sheet и с которым сравнивается expectedSheet.
 */
final class CharacterSheetDocument
{
    /**
     * Собирает снимок.
     *
     * @param CharacterValidation $validation Результат валидатора.
     * @param int $money Наличные.
     * @param array<int, array{ruleCode: string, current: int|array{base: int, size: int}}>|null $resources Typed resource rows.
     *
     * @return array<string, mixed> Sheet.
     */
    public function build(CharacterValidation $validation, int $money, ?array $resources = null): array
    {
        $sheet = [
            'abilityLevels' => $validation->getAbilityLevels(),
            'racialAbilityCodes' => $validation->getRacialAbilityCodes(),
            'osSurchargeTotal' => $validation->getOsSurchargeTotal(),
            'equippedModifiers' => $this->equipped($validation->getEquippedModifiers()),
            'coveredPaths' => $validation->getCoveredPaths(),
            'characteristicPurchases' => $this->purchases($validation->getPurchasedCharacteristics()),
            'characteristicPurchaseOs' => $this->purchaseOs($validation->getPurchasedCharacteristics()),
            'active' => $validation->isActive(),
            'money' => $money,
        ];

        if ($resources !== null) {
            $sheet['resources'] = $resources;
        }

        return $sheet;
    }

    /**
     * Расхождение expectedSheet — один problem derived.
     *
     * @param array<string, mixed> $sheet Снимок сервера.
     * @param array<mixed>|null $expectedSheet Снимок клиента или null.
     *
     * @return array<int, CharacterProblem> Пусто, если ключа не было или документы равны.
     */
    public function findMismatch(array $sheet, ?array $expectedSheet): array
    {
        if ($expectedSheet === null || $expectedSheet === $sheet) {
            return [];
        }

        return [new CharacterProblem('CHARACTER_SHEET', 'Expected sheet does not match the server sheet', 'derived', 'expectedSheet')];
    }

    /**
     * Надетые предметы в стабильном JSON.
     *
     * @param array<int, CharacterEquippedModifier> $modifiers Снимки.
     *
     * @return array<int, array<string, mixed>> Список.
     */
    private function equipped(array $modifiers): array
    {
        $rows = [];
        foreach ($modifiers as $modifier) {
            $rows[] = [
                'ruleCode' => $modifier->getRuleCode(),
                'strengthPenalty' => $modifier->getStrengthPenalty(),
                'maxAgility' => $modifier->getMaxAgility(),
                'characteristicLimits' => $this->limits($modifier->getCharacteristicLimits()),
            ];
        }

        return $rows;
    }

    /**
     * Закупки в форме документа. Цена и значение из правила.
     *
     * @param array<int, \Mifrial\Roleplay\Character\Dto\CharacterPurchasedCharacteristic> $purchases Ступени.
     *
     * @return array<int, array{characteristicCode: string, cost: int, value: array{base: int, size: int}}> Строки.
     */
    private function purchases(array $purchases): array
    {
        $rows = [];
        foreach ($purchases as $purchase) {
            $rows[] = [
                'characteristicCode' => $purchase->getCharacteristicCode(),
                'cost' => $purchase->getCost(),
                'value' => [
                    'base' => $purchase->getValue()->getBase(),
                    'size' => $purchase->getValue()->getSize(),
                ],
            ];
        }

        return $rows;
    }

    /**
     * Сумма цен ступеней.
     *
     * @param array<int, \Mifrial\Roleplay\Character\Dto\CharacterPurchasedCharacteristic> $purchases Ступени.
     *
     * @return int ОС.
     */
    private function purchaseOs(array $purchases): int
    {
        $total = 0;
        foreach ($purchases as $purchase) {
            $total += $purchase->getCost();
        }

        return $total;
    }

    /**
     * Лимиты в форме документа листа.
     *
     * @param array<int, \Mifrial\Roleplay\Character\Dto\CharacterCharacteristicLimit> $limits Потолки.
     *
     * @return array<int, array{characteristic_code: string, limit: int}> Строки.
     */
    private function limits(array $limits): array
    {
        $rows = [];
        foreach ($limits as $limit) {
            $rows[] = [
                'characteristic_code' => $limit->getCharacteristicCode(),
                'limit' => $limit->getLimit(),
            ];
        }

        return $rows;
    }
}
