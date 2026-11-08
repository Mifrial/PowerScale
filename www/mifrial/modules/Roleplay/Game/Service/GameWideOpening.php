<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Проверки открытия широкого удара.
 */
final class GameWideOpening
{
    /**
     * Создаёт проверки открытия.
     *
     * @param GameStrikeAccess $access Права.
     * @param GameActionRepository $actions Действия.
     * @param GameStrikeRules $rules Правила.
     * @param GameCardAvailability $availability Доступность карточки.
     * @param GameWideChoices $choices Choices NPC.
     * @param GameStrikeOpenChecks $checks Модификаторы и форма выбора.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeAccess $access,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeRules $rules,
        private readonly GameCardAvailability $availability,
        private readonly GameWideChoices $choices,
        private readonly GameStrikeOpenChecks $checks,
    ) {
    }

    /**
     * Проверяет открытие широкого удара.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return void
     *
     * @throws GameInvalidException Если удар уже открыт или выбор невалиден.
     */
    public function assert(GameRecord $game, int $battleId, array $choice): void
    {
        $this->access->assertRoster($battleId, $choice['attacker'], $choice['targets']);
        if ($this->actions->findOpenAny($battleId) !== null) {
            throw new GameInvalidException('Game wide strike is already open');
        }

        $npcChoices = $choice['attacker']['type'] === 'npc'
            ? $this->choices->of($game->getId(), $choice['attacker']['id'])
            : null;
        $this->checks($game, $choice, $npcChoices);
        $this->checks->assertModifiers($game, $choice);
        $this->inventory($game, $choice, $npcChoices);
    }

    /**
     * Карточка, цели и доступность действия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточка, цели или доступность невалидны.
     */
    private function checks(GameRecord $game, array $choice, ?array $npcChoices): void
    {
        $this->rules->checks->assertAttack(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemInventoryId'],
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $npcChoices,
            $choice['instanceIndex'] ?? 0,
        );
        $this->rules->checks->assertAttackTargets(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            count($choice['targets']),
        );
        $this->availability->assertAvailable(
            $game,
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $choice['actionRuleCode'],
        );
    }

    /**
     * Проверяет списание предмета или боеприпаса.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     *
     * @throws GameInvalidException Если профиль, предмет или боеприпас невалидны.
     */
    private function inventory(GameRecord $game, array $choice, ?array $npcChoices): void
    {
        $this->rules->spends->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $choice['attacker']['type'],
            $choice['attacker']['id'],
            $npcChoices,
        );
    }
}
