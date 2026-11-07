<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;

/**
 * Проверки выбора удара до записи.
 */
final class GameStrikeOpenChecks
{
    /**
     * Создаёт проверки выбора.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameCardAvailability $availability Доступность карточки.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameNpcRepository $npcs NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameCardAvailability $availability,
        private readonly GameStrikeSheets $sheets,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если строка чужая или документа нет.
     * @throws GameNotFoundException Если строки нет.
     */
    public function npcChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Модификаторы действия объявлены, принадлежат атакующему и применимы к удару.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор удара.
     *
     * @return void
     *
     * @throws GameInvalidException Если код чужой, объявлен не одиночным ударом или вне области.
     */
    public function assertModifiers(GameRecord $game, array $choice): void
    {
        $codes = $choice['modifiers'] ?? [];
        if (!is_array($codes) || $codes === []) {
            return;
        }

        if (is_array($choice['strikes'] ?? null) && $choice['strikes'] !== []) {
            throw new GameInvalidException('Game action modifier is not supported for multi-strike');
        }

        $actor = $this->actorOf($choice, 'Game action modifier is invalid');
        $listed = $this->listed($codes);
        $this->assertScopes($game, $actor, $listed);
    }

    /**
     * Проверяет карточку манёвра и отдаёт её строку удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Описание манёвра или пустой список.
     *
     * @throws GameInvalidException Если карточка недоступна или форма чужая.
     */
    public function maneuverOf(GameRecord $game, array $choice): array
    {
        $code = $choice['maneuverRuleCode'] ?? null;
        if ($code === null) {
            return [];
        }

        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game maneuver card is invalid');
        }

        if (is_array($choice['strikes'] ?? null) && count($choice['strikes']) > 1) {
            throw new GameInvalidException('Game maneuver supports a single strike');
        }

        $actor = $this->actorOf($choice, 'Game maneuver card is invalid');
        $this->availability->assertAvailable($game, $actor['type'], $actor['id'], $code);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : AbilityCombat::maneuver($rule->getSpecDocument());
        if ($spec === null) {
            throw new GameInvalidException('Game maneuver card is invalid');
        }

        return $spec->toArray() + ['ruleCode' => $code];
    }

    /**
     * Форма нескольких экземпляров одного оружия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     *
     * @throws GameInvalidException Если форма нарушена.
     */
    public function assertWeaponShape(GameRecord $game, array $choice, ?array $npcChoices): void
    {
        $strikes = is_array($choice['strikes'] ?? null) ? $choice['strikes'] : [];
        if ($strikes === []) {
            return;
        }

        $base = $this->baseOf($game, $choice);
        $shape = $this->shapeOf($strikes);
        $count = count($strikes);
        $this->assertShape($base, $shape, $count);
        $this->assertCount($game, $choice, $base, $count, $npcChoices);
        if (count($shape['instances']) !== $count) {
            throw new GameInvalidException('Game strike weapons repeat');
        }
    }

    /**
     * Карточка действия выбора.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return AbilityBase Карточка.
     *
     * @throws GameInvalidException Если карточки нет.
     */
    private function baseOf(GameRecord $game, array $choice): AbilityBase
    {
        $actor = $this->actorOf($choice, 'Game strike action is invalid');
        $base = $this->rules->cards->actionBaseOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actor['action'],
        );
        if ($base === null) {
            throw new GameInvalidException('Game strike action is invalid');
        }

        return $base;
    }

    /**
     * Оружие и экземпляры выбора.
     *
     * @param list<array<string, mixed>> $strikes Удары.
     *
     * @return array{codes: array<int|string, bool>, instances: array<string, bool>} Форма.
     */
    private function shapeOf(array $strikes): array
    {
        $codes = [];
        $instances = [];
        foreach ($strikes as $strike) {
            $codes[$strike['itemRuleCode']] = true;
            $instances[$strike['itemInventoryId'] . ':' . $strike['instanceIndex']] = true;
        }

        return ['codes' => $codes, 'instances' => $instances];
    }

    /**
     * Одинаковое или различное оружие и число ударов карточки.
     *
     * @param AbilityBase $base Карточка.
     * @param array{codes: array<int|string, bool>, instances: array<string, bool>} $shape Форма.
     * @param int $count Число ударов.
     *
     * @return void
     *
     * @throws GameInvalidException Если форма нарушена.
     */
    private function assertShape(AbilityBase $base, array $shape, int $count): void
    {
        if ($base->isSameWeapon() && count($shape['codes']) !== 1) {
            throw new GameInvalidException('Game strike same weapon is invalid');
        }

        if ($base->isDistinctWeapons() && count($shape['codes']) !== $count) {
            throw new GameInvalidException('Game strike distinct weapons are invalid');
        }

        $strikeCount = $base->getStrikeCount();
        if (is_int($strikeCount) && $strikeCount > 0 && $count !== $strikeCount) {
            throw new GameInvalidException('Game strike count is invalid');
        }
    }

    /**
     * Границы числа оружия карточки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param AbilityBase $base Карточка.
     * @param int $count Число ударов.
     * @param array<string, mixed>|null $npcChoices Choices NPC.
     *
     * @return void
     *
     * @throws GameInvalidException Если границы нарушены.
     */
    private function assertCount(
        GameRecord $game,
        array $choice,
        AbilityBase $base,
        int $count,
        ?array $npcChoices,
    ): void {
        $min = $base->getMinWeapons();
        if (is_int($min) && $count < $min) {
            throw new GameInvalidException('Game strike weapon count is invalid');
        }

        $max = $base->getMaxWeapons();
        if (!is_int($max) || $count <= $max) {
            return;
        }

        $actor = $this->actorOf($choice, 'Game strike action is invalid');
        $lifted = $this->rules->cards->liftsMaxWeapons(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actor['action'],
            $actor['type'],
            $actor['id'],
            $npcChoices,
        );
        if (!$lifted) {
            throw new GameInvalidException('Game strike weapon count is invalid');
        }
    }

    /**
     * Атакующий и форма выбора.
     *
     * @param array<string, mixed> $choice Выбор.
     * @param string $error Текст ошибки.
     *
     * @return array{type: string, id: int, action: string, profile: string} Атакующий.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function actorOf(array $choice, string $error): array
    {
        $attacker = $choice['attacker'] ?? null;
        $action = $choice['actionRuleCode'] ?? null;
        $profile = $choice['profileType'] ?? null;
        if (!is_array($attacker) || !is_string($action) || !is_string($profile)) {
            throw new GameInvalidException($error);
        }

        if (($attacker['type'] ?? null) !== 'character' && ($attacker['type'] ?? null) !== 'npc') {
            throw new GameInvalidException($error);
        }

        if (!is_int($attacker['id'] ?? null)) {
            throw new GameInvalidException($error);
        }

        return ['type' => $attacker['type'], 'id' => $attacker['id'], 'action' => $action, 'profile' => $profile];
    }

    /**
     * Список кодов модификаторов.
     *
     * @param array<int|string, mixed> $codes Коды.
     *
     * @return list<string> Коды.
     *
     * @throws GameInvalidException Если код битый.
     */
    private function listed(array $codes): array
    {
        if (!array_is_list($codes)) {
            throw new GameInvalidException('Game action modifier is invalid');
        }

        $listed = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                throw new GameInvalidException('Game action modifier is invalid');
            }

            $listed[] = $code;
        }

        return $listed;
    }

    /**
     * Область применения каждого модификатора.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int, action: string, profile: string} $actor Атакующий.
     * @param list<string> $codes Коды.
     *
     * @return void
     *
     * @throws GameInvalidException Если модификатор вне области удара.
     */
    private function assertScopes(GameRecord $game, array $actor, array $codes): void
    {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $modifiers = new GameActionModifiers();
        $levels = $this->sheets->abilityLevels($game, $actor['type'], $actor['id']);
        foreach ($codes as $code) {
            $modifier = $modifiers->find($slice, $actor['action'], $levels, $code);
            if (!$modifier['effect']->appliesTo($actor['profile'], 1)) {
                throw new GameInvalidException('Game action modifier scope is invalid');
            }
        }
    }
}
