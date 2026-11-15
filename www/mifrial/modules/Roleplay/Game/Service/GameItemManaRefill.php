<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ItemManaRefillEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Пополнение маны врождённых предметов-хранилищ по эффекту действия.
 *
 * Логика читает только спеку: эффект называет характеристику, а предметы
 * отбираются по признаку врождённости. Извлечённый кристалл не пополняется.
 */
final class GameItemManaRefill
{
    /**
     * Операции листа для эффектов `item_mana_refill`.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист актора.
     * @param AbilityActionSpec $spec Спека действия.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если характеристики или предмета нет.
     */
    public function operations(CharacterRuleSlice $slice, array $sheet, AbilityActionSpec $spec): array
    {
        $codes = [];
        foreach ($spec->getBase()->getActionEffects() as $effect) {
            if ($effect instanceof ItemManaRefillEffect) {
                $codes[] = $effect->getCharacteristicCode();
            }
        }

        if ($codes === []) {
            return [];
        }

        $operations = [];
        foreach ($codes as $code) {
            $amount = $this->characteristic($sheet, $code);
            $operations = [...$operations, ...$this->refill($slice, $sheet, $amount)];
        }

        if ($operations === []) {
            throw new GameInvalidException('Game action has no innate storage to refill');
        }

        return $operations;
    }

    /**
     * Явная правка маны одной строки хранилища.
     *
     * Кнопка ГМ: значение клампится по `manaMax` в операции листа, поэтому
     * форма принимает любое неотрицательное целое.
     *
     * @param array<string, mixed> $sheet Лист актора.
     * @param string $sourceKey Ключ строки `sheet.items`.
     * @param int $value Новое значение маны.
     *
     * @return array<string, mixed> Операция листа.
     *
     * @throws GameInvalidException Если строки нет.
     */
    public function setValue(array $sheet, string $sourceKey, int $value): array
    {
        $row = is_array($sheet['items'] ?? null) ? ($sheet['items'][$sourceKey] ?? null) : null;
        if (!is_array($row)) {
            throw new GameInvalidException('Game action mana storage is missing');
        }

        return [
            'kind' => 'setItemMana',
            'sourceKey' => $sourceKey,
            'value' => $value,
        ];
    }

    /**
     * Значение характеристики для прибавки.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код характеристики.
     *
     * @return int Мана.
     *
     * @throws GameInvalidException Если характеристики нет.
     */
    private function characteristic(array $sheet, string $code): int
    {
        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            if (!is_array($row) || ($row['characteristicCode'] ?? null) !== $code) {
                continue;
            }

            $value = $row['value'] ?? null;
            $base = is_array($value) ? ($value['base'] ?? null) : null;
            $size = is_array($value) ? ($value['size'] ?? null) : null;
            if (is_int($base) && is_int($size)) {
                return max(0, (new CharacteristicNumber($base, $size))->toInteger());
            }
        }

        throw new GameInvalidException('Game action refill characteristic is missing');
    }

    /**
     * Операции пополнения по строкам `sheet.items`.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param int $amount Прибавка.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function refill(CharacterRuleSlice $slice, array $sheet, int $amount): array
    {
        $operations = [];
        foreach (is_array($sheet['items'] ?? null) ? $sheet['items'] : [] as $key => $row) {
            if (!is_string($key) || !is_array($row)) {
                continue;
            }

            $ruleCode = $row['ruleCode'] ?? null;
            if (!is_string($ruleCode) || !$this->isInnateStorage($slice, $ruleCode)) {
                continue;
            }

            $operations[] = [
                'kind' => 'refillItemMana',
                'sourceKey' => $key,
                'amount' => $amount,
            ];
        }

        return $operations;
    }

    /**
     * Живой предмет — врождённое хранилище.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код предмета.
     *
     * @return bool true, если врождённое хранилище.
     */
    private function isInnateStorage(CharacterRuleSlice $slice, string $code): bool
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof ItemSpec && $spec->isInnate() && $spec->getStorage() !== null;
    }
}
