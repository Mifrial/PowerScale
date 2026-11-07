<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Снимок атакующего, жетон и гейт добивания.
 */
final class GameStrikeOpenView
{
    /**
     * Создаёт представление открытия.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Снимки листов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Снимок атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Снимок.
     *
     * @throws GameInvalidException Если вид атакующего битый.
     */
    public function snapshot(GameRecord $game, array $choice): array
    {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return $kind === 'character'
            ? $this->sheets->getCharacterSnapshot($id)
            : $this->sheets->getNpcSnapshot($game->getId(), $id);
    }

    /**
     * Данные жетона для строки удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $snapshot Снимок атакующего.
     *
     * @return array<string, mixed> Данные.
     */
    public function tokenInfo(GameRecord $game, array $choice, array $snapshot): array
    {
        $raw = $choice['tokenSpend'] ?? null;
        if ($raw === null) {
            return [];
        }

        $amount = is_array($raw) ? ($raw['amount'] ?? 0) : $raw;
        if (!is_int($amount) || $amount < 1) {
            return [];
        }

        $sheet = is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [];
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $resource = (new GameConcentration())->resourceCode($slice, $sheet) ?? '';

        return [
            'resource' => $resource,
            'amount' => $amount,
            'source' => is_array($raw) && is_string($raw['source'] ?? null) ? $raw['source'] : $resource,
            'mode' => is_array($raw) && is_string($raw['mode'] ?? null) ? $raw['mode'] : 'advantage',
        ];
    }

    /**
     * Гейт добивания по снимку прошлого удара.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если добивание не разрешено.
     */
    public function assertFollowUp(GameRecord $game, GameBattleRecord $battle, array $choice): void
    {
        $attacker = $choice['attacker'] ?? null;
        $actionRuleCode = $choice['actionRuleCode'] ?? null;
        if (
            !is_array($attacker)
            || !is_string($attacker['type'] ?? null)
            || !is_int($attacker['id'] ?? null)
            || !is_string($actionRuleCode)
        ) {
            return;
        }

        (new GameLastStrikeSnapshot())->assertFollowUp(
            $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            $actionRuleCode,
            $battle->getPendingActionEffects(),
            $attacker['type'],
            $attacker['id'],
            $this->targetKeys($choice),
        );
    }

    /**
     * Ключи целей выбора.
     *
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<int, string> Ключи.
     */
    private function targetKeys(array $choice): array
    {
        $targets = is_array($choice['targets'] ?? null) ? $choice['targets'] : [$choice['defender'] ?? null];
        $keys = [];
        foreach ($targets as $target) {
            if (is_array($target) && is_string($target['type'] ?? null) && is_int($target['id'] ?? null)) {
                $keys[] = $target['type'] . ':' . $target['id'];
            }
        }

        return $keys;
    }
}
