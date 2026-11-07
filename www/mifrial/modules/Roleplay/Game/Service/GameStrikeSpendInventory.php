<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Списание строки инвентаря объявленного действия.
 */
final class GameStrikeSpendInventory
{
    /**
     * Создаёт списание инвентаря.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeRules $rules)
    {
    }

    /**
     * Списывает предмет или боеприпас открытого объявления.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор атаки.
     * @param array<string, mixed> $choices Выборы листа.
     * @param string $kind Вид атакующего.
     * @param int $id Атакующий.
     * @param bool $open Открытый удар без долга.
     *
     * @return array{kind: string, inventoryId: int, ruleCode: string}|null Списание или null.
     */
    public function resolve(
        GameRecord $game,
        array $choice,
        array $choices,
        string $kind,
        int $id,
        bool $open,
    ): ?array {
        if (!$open || !$this->withoutPush($game, $choice)) {
            return null;
        }

        return $this->rules->spends->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $kind,
            $id,
            $kind === 'npc' ? $choices : null,
        );
    }

    /**
     * Тратит ли карточка предмет без толчка.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор атаки.
     *
     * @return bool Признак.
     */
    private function withoutPush(GameRecord $game, array $choice): bool
    {
        return $this->rules->cards->pushOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
        ) === null;
    }
}
