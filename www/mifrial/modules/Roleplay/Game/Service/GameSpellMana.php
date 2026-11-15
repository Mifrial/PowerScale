<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Мана предмета-хранилища при касте.
 *
 * Цена каста — `[используемая мощь × Создание в ходах]` с округлением вверх,
 * где ход равен лимиту ресурса хода. Живёт только в `sheet.items`; ядро
 * маны не имеет, поэтому для него цена нулевая.
 */
final class GameSpellMana
{
    private readonly GameSpellCharge $charge;

    /**
     * Создаёт расчёт.
     *
     * @return void
     */
    public function __construct()
    {
        $this->charge = new GameSpellCharge();
    }

    /**
     * План траты маны или null, если источник не хранилище.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $sheet Лист кастера.
     * @param string $sourceKey Ключ источника мощи.
     * @param CharacteristicNumber $power Используемая мощь.
     * @param int $spent Создание в ОД.
     * @param string $turnCode Код ресурса хода.
     *
     * @return array{sourceKey: string, cost: int, before: int, after: int}|null План или null.
     *
     * @throws GameInvalidException Если маны не хватает.
     */
    public function plan(
        CharacterRuleSlice $slice,
        array $sheet,
        string $sourceKey,
        CharacteristicNumber $power,
        int $spent,
        string $turnCode,
    ): ?array {
        $plan = $this->quote($slice, $sheet, $sourceKey, $power, $spent, $turnCode);
        if ($plan !== null && $plan['cost'] > $plan['before']) {
            throw new GameInvalidException('Game spell mana is not enough');
        }

        return $plan;
    }

    /**
     * План траты маны без отказа при нехватке: решение принимает вызывающий.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $sheet Лист кастера.
     * @param string $sourceKey Ключ источника мощи.
     * @param CharacteristicNumber $power Используемая мощь.
     * @param int $spent Создание в ОД.
     * @param string $turnCode Код ресурса хода.
     *
     * @return array{sourceKey: string, cost: int, before: int, after: int}|null План или null.
     */
    public function quote(
        CharacterRuleSlice $slice,
        array $sheet,
        string $sourceKey,
        CharacteristicNumber $power,
        int $spent,
        string $turnCode,
    ): ?array {
        $row = $this->row($sheet, $sourceKey);
        if ($row === null) {
            return null;
        }

        $limit = $turnCode === ''
            ? 0
            : $this->charge->turnLimit($sheet, $slice, $turnCode);
        $cost = $this->cost($power, $spent, $limit);
        $before = is_int($row['mana'] ?? null) ? $row['mana'] : 0;

        return [
            'sourceKey' => $sourceKey,
            'cost' => $cost,
            'before' => $before,
            'after' => $before - $cost,
        ];
    }

    /**
     * Операции листа для списания маны.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если строки нет или маны мало.
     */
    public function spendOperations(GameSpellCastPrepared $prepared): array
    {
        $plan = $prepared->manaSpend;
        if ($plan === null) {
            return [];
        }

        $row = $this->row($prepared->casterSheet, $plan['sourceKey']);
        if ($row === null) {
            throw new GameInvalidException('Game spell mana source is missing');
        }

        $before = is_int($row['mana'] ?? null) ? $row['mana'] : 0;
        if ($plan['cost'] > $before) {
            throw new GameInvalidException('Game spell mana is not enough');
        }

        return $this->operation($plan['sourceKey'], $plan['cost']);
    }

    /**
     * Операция списания маны.
     *
     * @param string $sourceKey Ключ источника.
     * @param int $cost Мана.
     *
     * @return list<array<string, mixed>> Операции.
     */
    public function operation(string $sourceKey, int $cost): array
    {
        if ($cost <= 0) {
            return [];
        }

        return [[
            'kind' => 'spendItemMana',
            'sourceKey' => $sourceKey,
            'amount' => $cost,
        ]];
    }

    /**
     * Строка хранилища в листе.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $sourceKey Ключ источника.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    public function row(array $sheet, string $sourceKey): ?array
    {
        $items = $sheet['items'] ?? null;
        if (!is_array($items)) {
            return null;
        }

        $row = $items[$sourceKey] ?? null;

        return is_array($row) ? $row : null;
    }

    /**
     * Цена каста в мане: мощь, умноженная на Создание в целых ходах.
     *
     * @param CharacteristicNumber $power Используемая мощь.
     * @param int $spent Создание в ОД.
     * @param int $turnLimit Лимит ресурса хода.
     *
     * @return int Мана.
     */
    public function cost(CharacteristicNumber $power, int $spent, int $turnLimit): int
    {
        if ($spent <= 0 || $turnLimit <= 0) {
            return 0;
        }

        $turns = (int) ceil($spent / $turnLimit);

        return $power->toInteger() * $turns;
    }
}
