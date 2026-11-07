<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeOpenRow;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Достижимость и условия строк открытого удара.
 */
final class GameStrikeOpenTargets
{
    /**
     * Создаёт проверку строк.
     *
     * @param GameStrikeRules $rules Правила.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeRules $rules)
    {
    }

    /**
     * Проверяет достижимость и условия строк удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $snapshot Снимок атакующего.
     * @param list<array<string, mixed>> $targets Строки.
     * @param string $actionCode Карточка действия.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function assert(
        GameRecord $game,
        array $choice,
        array $snapshot,
        array $targets,
        string $actionCode,
        string $attackerKind,
        int $attackerId,
    ): void {
        $push = $this->rules->cards->pushOf($game->getSpaceId(), $game->getRulesRevision(), $actionCode);
        if ($push !== null) {
            return;
        }

        $context = $this->context($game, $snapshot, $attackerKind);
        foreach ($targets as $target) {
            $this->row($game, $context, $target, $actionCode, $attackerKind, $attackerId);
        }
    }

    /**
     * Общий снимок атакующего для строк.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $snapshot Снимок.
     * @param string $attackerKind Вид атакующего.
     *
     * @return array{slice: CharacterRuleSlice, npcSheet: array<string, mixed>|null, npcChoices: array<string, mixed>|null, attackerChoices: array<string, mixed>, abilityLevels: array<string, mixed>, version: int|null} Контекст.
     */
    private function context(GameRecord $game, array $snapshot, string $attackerKind): array
    {
        $sheet = is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [];
        $choices = is_array($snapshot['sheet']['choices'] ?? null) ? $snapshot['sheet']['choices'] : [];

        return [
            'slice' => $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            'npcSheet' => $attackerKind === 'npc' ? $sheet : null,
            'npcChoices' => $attackerKind === 'npc' ? $choices : null,
            'attackerChoices' => $choices,
            'abilityLevels' => is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [],
            'version' => is_int($snapshot['actualVersion'] ?? null) ? $snapshot['actualVersion'] : null,
        ];
    }

    /**
     * Проверяет одну строку удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $context Контекст.
     * @param array<string, mixed> $target Строка.
     * @param string $actionCode Карточка действия.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function row(
        GameRecord $game,
        array $context,
        array $target,
        string $actionCode,
        string $attackerKind,
        int $attackerId,
    ): void {
        $row = $this->openRow($target, $actionCode, $attackerKind, $attackerId);
        $this->rules->distance->assertConditions(
            $this->rules->damage->attack($this->strikeTarget($game, $context, $row)),
            is_array($target['conditions'] ?? null)
                ? $target['conditions']
                : (new GameStrikeConditions())->defaults(),
            $actionCode,
        );
    }

    /**
     * Профиль строки из выбора.
     *
     * @param array<string, mixed> $target Строка.
     * @param string $actionCode Карточка действия.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     *
     * @return GameStrikeOpenRow Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function openRow(array $target, string $actionCode, string $attackerKind, int $attackerId): GameStrikeOpenRow
    {
        $profileType = $target['profile_type'] ?? null;
        $profileIndex = $target['profile_index'] ?? null;
        $itemInventoryId = $target['item_inventory_id'] ?? null;
        $itemRuleCode = $target['item_rule_code'] ?? null;
        if (
            !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
            || !is_string($itemRuleCode)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $row = new GameStrikeOpenRow();
        $row->profileType = $profileType;
        $row->profileIndex = $profileIndex;
        $row->itemInventoryId = $itemInventoryId;
        $row->itemRuleCode = $itemRuleCode;
        $row->actionCode = $actionCode;
        $row->attackerKind = $attackerKind;
        $row->attackerId = $attackerId;

        return $row;
    }

    /**
     * Цель удара с модификатором характеристики.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $context Контекст.
     * @param GameStrikeOpenRow $row Строка.
     *
     * @return GameStrikeTarget Цель.
     */
    private function strikeTarget(GameRecord $game, array $context, GameStrikeOpenRow $row): GameStrikeTarget
    {
        return GameStrikeTarget::of(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row->itemRuleCode,
            $row->profileType,
            $row->profileIndex,
            $row->itemInventoryId,
            $row->attackerKind,
            $row->attackerId,
            $context['npcSheet'],
            $context['npcChoices'],
            $context['version'],
            $this->modifier($game, $context, $row),
            0,
            0,
        );
    }

    /**
     * Модификатор характеристики действия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $context Контекст.
     * @param GameStrikeOpenRow $row Строка.
     *
     * @return int Модификатор.
     */
    private function modifier(GameRecord $game, array $context, GameStrikeOpenRow $row): int
    {
        return $this->rules->modifiers->actionCharacteristicModifier(
            $context['slice'],
            $row->actionCode,
            $row->profileType,
            1,
            $this->filter($game, $context, $row),
            $context['abilityLevels'],
        );
    }

    /**
     * Контекст фильтра характеристик атаки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $context Контекст.
     * @param GameStrikeOpenRow $row Строка.
     *
     * @return array<string, mixed> Контекст фильтра.
     */
    private function filter(GameRecord $game, array $context, GameStrikeOpenRow $row): array
    {
        return $this->rules->outcome->attackFilterContext(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $row->itemRuleCode,
            $row->profileType,
            $row->profileIndex,
            $context['attackerChoices'],
            $row->itemInventoryId,
        );
    }
}
