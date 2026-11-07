<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;

/**
 * Долг объявленного действия: проверка, план и удержание слота.
 */
final class GameStrikeSpendDebt
{
    /**
     * Проверяет долг и планирует оплату.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param array<string, mixed> $choice Объявление.
     * @param string|null $lane Полоса удара.
     * @param string $kind Вид атакующего.
     * @param int $id Атакующий.
     *
     * @return array{key: string, planned: array{spends: array<int, ResourceSpend>, open: bool,
     *               slot: array<string, mixed>|null}} План.
     */
    public function plan(
        CharacterRuleSlice $slice,
        GameBattleRecord $battle,
        array $sheet,
        array $spends,
        array $choice,
        ?string $lane,
        string $kind,
        int $id,
    ): array {
        $debt = new GameActionDebt();
        $key = $debt->key($kind, $id);
        $debt->assertFree($battle->getActionDebt(), $key);

        return [
            'key' => $key,
            'planned' => $debt->plan($slice, $sheet, $spends, $debt->refillCode($slice), 'strike', $choice, $lane),
        ];
    }

    /**
     * Удерживает слот долга, если по карточке остался долг.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $key Ключ атакующего.
     * @param array<string, mixed> $planned План.
     *
     * @return array<string, mixed>|null Карта долга или null.
     */
    public function hold(GameBattleRecord $battle, string $key, array $planned): ?array
    {
        if (($planned['open'] ?? false) === true) {
            return null;
        }

        $debt = $battle->getActionDebt();
        $debt[$key] = $planned['slot'];

        return $debt;
    }
}
