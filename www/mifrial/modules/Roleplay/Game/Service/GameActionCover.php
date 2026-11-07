<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;

/**
 * Прикрытие чужого удара: заявка без списания.
 */
final class GameActionCover
{
    /**
     * Создаёт сценарий.
     *
     * @param GameActionPorts $ports Порты команды.
     * @param GameActionValue $value Значения строк.
     * @param GameActionEffectInput $effects Payload эффектов.
     * @param GameActionBattle $battle Бой.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPorts $ports,
        private readonly GameActionValue $value,
        private readonly GameActionEffectInput $effects,
        private readonly GameActionBattle $battle,
    ) {
    }

    /**
     * Объявляет прикрытие: заявка без списания.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $target Цель реакции.
     * @param GameBattleRecord $battle Бой.
     * @param CoverAlly $cover Узел прикрытия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если защита недоступна или цена выше прикрытия.
     */
    public function declare(
        GameRecord $game,
        int $actorUserId,
        array $input,
        array $target,
        GameBattleRecord $battle,
        CoverAlly $cover,
    ): array {
        $effect = $this->effect($input, $target, $cover);
        $choices = $this->choices($game, $input, $effect);
        $cost = $this->price($game, $input, $battle, $effect, $choices);
        $coverers = $target['coverers'];
        $coverers[] = $this->coverer($input, $effect, $cost, $target['strikeId']);
        $saved = $this->save($battle, $input, $target['strikeId'], $coverers);
        $this->post($game, $actorUserId, $input, $target, $battle, $effect, $cost);

        return [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => null,
            'strikeId' => $target['strikeId'],
            'cover' => $effect['reaction'],
            'spent' => 0,
            'declared' => true,
        ];
    }

    /**
     * Выбранная защита прикрытия.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $target Цель реакции.
     * @param CoverAlly $cover Узел прикрытия.
     *
     * @return array<string, mixed> Эффект.
     *
     * @throws GameInvalidException Если защита чужая.
     */
    private function effect(array $input, array $target, CoverAlly $cover): array
    {
        $effect = $this->effects->coverInput($input['context']);
        if (!in_array($effect['reaction'], $cover->getReactions(), true)) {
            throw new GameInvalidException('Game strike cover reaction is invalid');
        }

        if ($this->value->strikeParticipant($target['row']['target_type'] ?? null, $target['row']['target_id'] ?? null) === null) {
            throw new GameInvalidException('Game strike cover target is invalid');
        }

        return $effect;
    }

    /**
     * Выбор цели и лист актора.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $effect Эффект.
     *
     * @return array{actorChoices: array<string, mixed>, actorSheet: array<string, mixed>} Выбор.
     *
     * @throws GameInvalidException Если блок чужой.
     */
    private function choices(GameRecord $game, array $input, array $effect): array
    {
        $choices = new GameStrikeTargetChoices($this->ports->rules, $this->ports->sheets);
        $actorChoices = $choices->targetChoices($game, $input['actor']);
        $actorSheet = $this->ports->sheets->getTargetSheet($game, $input['actor']);
        if ($effect['reaction'] === 'block') {
            $this->ports->rules->checks->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actorChoices,
                $effect['blockItemInventoryId'],
                $effect['blockItemProfileIndex'],
                $effect['blockItemRuleCode'],
            );
        }

        return ['actorChoices' => $actorChoices, 'actorSheet' => $actorSheet];
    }

    /**
     * Цена защиты прикрытия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $effect Эффект.
     * @param array{actorChoices: array<string, mixed>, actorSheet: array<string, mixed>} $choices Выбор.
     *
     * @return int Цена.
     *
     * @throws GameInvalidException Если цена выше прикрытия.
     */
    private function price(
        GameRecord $game,
        array $input,
        GameBattleRecord $battle,
        array $effect,
        array $choices,
    ): int {
        $quote = $this->ports->perform->quote(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $battle->getPendingActionEffects(),
            false,
            [],
            null,
        );
        $cost = $this->value->spentOf($quote['spends']);
        $this->ports->perform->assertSpends($game, $quote['document']['sheet'], $quote['spends']);
        $this->assertCost($game, $effect, $choices, $cost);

        return $cost;
    }

    /**
     * Реакция прикрытия не дороже прикрытия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $effect Эффект.
     * @param array{actorChoices: array<string, mixed>, actorSheet: array<string, mixed>} $choices Выбор.
     * @param int $cost Цена прикрытия.
     *
     * @return void
     *
     * @throws GameInvalidException Если реакция дороже.
     */
    private function assertCost(GameRecord $game, array $effect, array $choices, int $cost): void
    {
        $reactionSpends = $this->ports->rules->spends->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $effect['reaction'],
            $effect['reaction'] === 'block' ? $effect['blockItemInventoryId'] : null,
            $effect['blockItemRuleCode'],
            $choices['actorChoices'],
            $choices['actorSheet'],
        );
        if ($this->value->spentOf($reactionSpends) > $cost) {
            throw new GameInvalidException('Game strike cover reaction is too expensive');
        }
    }

    /**
     * Запись прикрытия в строку.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $effect Эффект.
     * @param int $cost Цена.
     * @param int $strikeId Строка.
     *
     * @return array<string, mixed> Запись.
     */
    private function coverer(array $input, array $effect, int $cost, int $strikeId): array
    {
        return [
            'actor' => $input['actor'],
            'ruleCode' => $input['actionRuleCode'],
            'reaction' => $effect['reaction'],
            'blockItemInventoryId' => $effect['blockItemInventoryId'],
            'blockItemProfileIndex' => $effect['blockItemProfileIndex'],
            'blockItemRuleCode' => $effect['blockItemRuleCode'],
            'expectedSheetVersion' => $input['context']['expectedSheetVersion'],
            'cost' => $cost,
            'reactedAt' => $strikeId,
        ];
    }

    /**
     * Сохраняет заявку прикрытия.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param int $strikeId Строка.
     * @param list<array<string, mixed>> $coverers Заявившиеся.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws GameBattleConflictException Если версия другая.
     */
    private function save(
        GameBattleRecord $battle,
        array $input,
        int $strikeId,
        array $coverers,
    ): GameBattleRecord {
        return $this->battle->saveStrike($battle, $input, $strikeId, 'coverers', $coverers);
    }

    /**
     * Сообщение о прикрытии.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $target Цель реакции.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $effect Эффект.
     * @param int $cost Цена.
     *
     * @return void
     */
    private function post(
        GameRecord $game,
        int $actorUserId,
        array $input,
        array $target,
        GameBattleRecord $battle,
        array $effect,
        int $cost,
    ): void {
        $threadId = $this->value->chatId($target);
        $this->ports->chat->postStrikeCover(
            $game,
            $actorUserId,
            $input['actor']['type'],
            $input['actor']['id'],
            $battle,
            $threadId,
            $input['actionRuleCode'],
            $target['target'],
            $effect['reaction'],
            $effect['blockItemRuleCode'],
            $cost,
        );
    }
}
