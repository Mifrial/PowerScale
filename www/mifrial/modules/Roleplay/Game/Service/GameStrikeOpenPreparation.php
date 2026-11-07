<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Подготовка выбора удара до транзакции.
 */
final class GameStrikeOpenPreparation
{
    /**
     * Создаёт подготовку выбора.
     *
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeOpenChecks $checks Проверки выбора.
     * @param GameCardAvailability $availability Доступность карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameProcessFlow $processes,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeOpenChecks $checks,
        private readonly GameCardAvailability $availability,
    ) {
    }

    /**
     * Проверяет выбор и обогащает его процессом, манёвром и тратами.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Подготовленный выбор.
     */
    public function prepare(GameRecord $game, GameBattleRecord $battle, array $choice): array
    {
        $choice = $this->applyProcess($game, $battle, $choice);
        $npcChoices = $this->npcChoices($game, $choice);
        $this->assertStrikes($game, $choice, $npcChoices);
        $this->checks->assertWeaponShape($game, $choice, $npcChoices);
        $this->assertAvailable($game, $choice);
        $choice['maneuver'] = $this->checks->maneuverOf($game, $choice);
        $this->checks->assertModifiers($game, $choice);
        $this->applyInventorySpend($game, $choice, $npcChoices);

        return $choice;
    }

    /**
     * Готовит шаг процесса, если он объявлен.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Выбор.
     */
    private function applyProcess(GameRecord $game, GameBattleRecord $battle, array $choice): array
    {
        if (($choice['processRuleCode'] ?? null) === null) {
            return $choice;
        }

        $process = $this->processes->prepare(
            $game,
            $battle,
            $choice['attacker'],
            $choice['processRuleCode'],
            is_string($choice['processStepCode'] ?? null) ? $choice['processStepCode'] : null,
            'strike',
            is_array($choice['defender'] ?? null) ? $choice['defender'] : null,
        );
        if ($process === null) {
            return $choice;
        }

        $choice['actionRuleCode'] = $process['actionCode'];
        $choice['processContext'] = $process;
        $choice['processStepCosts'] = $process['costs'];
        $choice['repeatWeaponUses'] = $this->processes->priorWeaponUses(
            $process['session'] ?? null,
            $process['spec'],
            $this->processes->weaponKey($choice),
        );

        return $choice;
    }

    /**
     * Choices NPC атакующего или null.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed>|null Choices.
     */
    private function npcChoices(GameRecord $game, array $choice): ?array
    {
        $attacker = $choice['attacker'] ?? null;
        if (!is_array($attacker) || ($attacker['type'] ?? null) !== 'npc' || !is_int($attacker['id'] ?? null)) {
            return null;
        }

        return $this->checks->npcChoices($game->getId(), $attacker['id']);
    }

    /**
     * Проверки карточек удара по каждой строке.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     */
    private function assertStrikes(GameRecord $game, array $choice, ?array $npcChoices): void
    {
        $strikes = is_array($choice['strikes'] ?? null) ? $choice['strikes'] : [$choice];
        foreach ($strikes as $strike) {
            $this->rules->checks->assertAttack(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $choice['actionRuleCode'],
                $strike['itemInventoryId'],
                $strike['itemRuleCode'],
                $strike['profileType'],
                $strike['profileIndex'],
                $choice['attacker']['type'],
                $choice['attacker']['id'],
                $npcChoices,
                $strike['instanceIndex'] ?? 0,
            );
        }

        $this->rules->checks->assertAttackTargets(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            1,
        );
    }

    /**
     * Доступность карточки действия атакующему.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     */
    private function assertAvailable(GameRecord $game, array $choice): void
    {
        $attacker = $choice['attacker'];
        $this->availability->assertAvailable(
            $game,
            $attacker['type'],
            $attacker['id'],
            $choice['actionRuleCode'],
        );
    }

    /**
     * Списывает боеприпасы, если действие не толчок.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     */
    private function applyInventorySpend(GameRecord $game, array $choice, ?array $npcChoices): void
    {
        if ($this->rules->cards->pushOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
        ) !== null) {
            return;
        }

        $attacker = $choice['attacker'];
        $this->rules->spends->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $attacker['type'],
            $attacker['id'],
            $attacker['type'] === 'npc' ? $npcChoices : null,
        );
    }
}
