<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ReactionModifier;
use Mifrial\Roleplay\Rule\Spec\AbilityTactics;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SupportModifier;
use Mifrial\Roleplay\Rule\Spec\SupportSpecs;

/**
 * Реакция в открытом ударе: поддержка и прикрытие.
 */
final class GameActionReaction
{
    /**
     * Создаёт сценарий.
     *
     * @param GameActionPorts $ports Порты команды.
     * @param GameActionInput $input Разбор тела.
     * @param GameActionBattle $battle Бой.
     * @param GameActionValue $value Значения строк.
     * @param GameActionCover $cover Прикрытие.
     * @param GameActionEffectInput $effects Payload эффектов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPorts $ports,
        private readonly GameActionInput $input,
        private readonly GameActionBattle $battle,
        private readonly GameActionValue $value,
        private readonly GameActionCover $cover,
        private readonly GameActionEffectInput $effects,
    ) {
    }

    /**
     * Проверяет открытый удар и записывает реакцию в его строку.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED.
     * @throws GameInvalidException Если реакция отвергнута.
     */
    public function declare(GameRecord $game, int $actorUserId, bool $editAll, array $body): array
    {
        $input = $this->input->reactionInput($body);
        $this->ports->access->assertAttacker($game, $actorUserId, $editAll, $input['actor']);
        $sessionId = $this->ports->sessions->findSessionId($game->getId());
        $battle = $this->battle->battleOf($sessionId, $input['battleId'], $input['expectedBattle']);
        $this->ports->access->assertRoster($input['battleId'], $input['actor'], []);
        $target = $this->target($input);
        $card = $this->card($game, $input, $target['attacker'], $target['target']);
        $this->assertNotDeclared($target['row'], $input);

        if ($card['cover'] !== null) {
            return $this->cover->declare($game, $actorUserId, $input, $target, $battle, $card['cover']);
        }

        return $this->declareSupport($game, $actorUserId, $input, $target, $battle, $card['support']);
    }

    /**
     * Открытый удар и его строки.
     *
     * @param int $battleId Бой.
     *
     * @return array{open: array<string, mixed>|null, strikes: list<array<string, mixed>>, wide: bool} Открытый удар.
     */
    private function openOf(int $battleId): array
    {
        $open = $this->ports->actions->findOpenWithStrikes($battleId, 'strike');
        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        if ($open !== null && count($strikes) === 1) {
            return ['open' => $open, 'strikes' => $strikes, 'wide' => false];
        }

        $open = $this->ports->actions->findOpenWithStrikes($battleId, 'wide_strike');

        return [
            'open' => $open,
            'strikes' => is_array($open['strikes'] ?? null) ? $open['strikes'] : [],
            'wide' => true,
        ];
    }

    /**
     * Строка реакции.
     *
     * @param array<string, mixed>|null $open Открытый удар.
     * @param list<array<string, mixed>> $strikes Строки.
     * @param bool $wide Широкий удар.
     * @param array<string, mixed> $input Вход.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если реакция недоступна.
     */
    private function targetRow(?array $open, array $strikes, bool $wide, array $input): array
    {
        $row = $wide
            ? $this->value->rowOfTarget($strikes, $input['context'])
            : (is_array($strikes[0] ?? null) ? $strikes[0] : null);
        if ($open === null || $row === null) {
            throw new GameInvalidException('Game strike reaction is not available');
        }

        if (($row['reaction'] ?? null) !== null) {
            throw new GameInvalidException('Game strike reaction is already closed');
        }

        return $row;
    }

    /**
     * Строка, участники и уже заявленные эффекты.
     *
     * @param array<string, mixed> $input Вход.
     *
     * @return array<string, mixed> Цель реакции.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function target(array $input): array
    {
        $opened = $this->openOf($input['battleId']);
        $row = $this->targetRow($opened['open'], $opened['strikes'], $opened['wide'], $input);
        $strikeId = $row['id'] ?? null;
        $attacker = $this->value->strikeParticipant(
            $opened['open']['attacker_kind'] ?? null,
            $opened['open']['attacker_id'] ?? null,
        );
        $target = $this->value->strikeParticipant($row['target_type'] ?? null, $row['target_id'] ?? null);
        if (!is_int($strikeId) || $attacker === null || $target === null) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        return [
            'open' => $opened['open'],
            'row' => $row,
            'wide' => $opened['wide'],
            'strikeId' => $strikeId,
            'attacker' => $attacker,
            'target' => $target,
            'supporters' => $this->value->attachments($row, 'supporters'),
            'coverers' => $this->value->attachments($row, 'coverers'),
        ];
    }

    /**
     * Карточка реакции.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array{type: string, id: int} $attacker Атакующий.
     * @param array{type: string, id: int} $target Защитник.
     *
     * @return array<string, mixed> Карточка.
     *
     * @throws GameInvalidException Если карточка чужая.
     */
    private function card(GameRecord $game, array $input, array $attacker, array $target): array
    {
        $slice = $this->ports->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $rule = $slice->findLive($input['actionRuleCode']);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            throw new GameInvalidException('Game strike reaction card is invalid');
        }

        $reaction = $spec->getBase()->getReaction();
        if ($reaction === null || $spec->getOperations() !== [] || $spec->getBase()->getActionEffects() !== []) {
            throw new GameInvalidException('Game strike reaction card is invalid');
        }

        $role = $this->role($input['actor'], $attacker, $target);
        if (
            $reaction->getTrigger() !== ReactionModifier::TRIGGER_STRIKE
            || $reaction->getWindow() !== ReactionModifier::WINDOW_BEFORE_DEFENSE
            || !$reaction->allows($role)
        ) {
            throw new GameInvalidException('Game strike reaction actor is invalid');
        }

        return $this->effect($rule, $reaction, $role);
    }

    /**
     * Роль актора реакции.
     *
     * @param array{type: string, id: int} $actor Актор.
     * @param array{type: string, id: int} $attacker Атакующий.
     * @param array{type: string, id: int} $target Защитник.
     *
     * @return string attacker, defender или third_party.
     */
    private function role(array $actor, array $attacker, array $target): string
    {
        if ($actor === $attacker) {
            return 'attacker';
        }

        return $actor === $target ? 'defender' : 'third_party';
    }

    /**
     * Исполняемый эффект карточки.
     *
     * @param \Mifrial\Roleplay\Character\Dto\CharacterResolvedRule $rule Правило.
     * @param ReactionModifier $reaction Реакция.
     * @param string $role Роль.
     *
     * @return array<string, mixed> Эффект.
     *
     * @throws GameInvalidException Если эффектов нет или их два.
     */
    private function effect(
        \Mifrial\Roleplay\Character\Dto\CharacterResolvedRule $rule,
        ReactionModifier $reaction,
        string $role,
    ): array {
        $support = SupportSpecs::read($rule->getSpecDocument());
        $cover = AbilityTactics::cover($rule->getSpecDocument());
        if ($support === null && $cover === null) {
            throw new GameInvalidException('Game strike reaction has no executable effect');
        }

        if ($support !== null && $cover !== null) {
            throw new GameInvalidException('Game strike reaction effect is ambiguous');
        }

        return ['reaction' => $reaction, 'role' => $role, 'support' => $support, 'cover' => $cover];
    }

    /**
     * Реакция ещё не заявлена этим актором.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $input Вход.
     *
     * @return void
     *
     * @throws GameInvalidException Если уже заявлена.
     */
    private function assertNotDeclared(array $row, array $input): void
    {
        $entries = array_merge(
            $this->value->attachments($row, 'supporters'),
            $this->value->attachments($row, 'coverers'),
        );
        foreach ($entries as $entry) {
            $actor = $entry['actor'] ?? null;
            if (
                is_array($actor)
                && ($actor['type'] ?? null) === $input['actor']['type']
                && ($actor['id'] ?? null) === $input['actor']['id']
            ) {
                throw new GameInvalidException('Game strike reaction is already declared');
            }
        }
    }

    /**
     * Объявляет поддержку.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $target Цель реакции.
     * @param GameBattleRecord $battle Бой.
     * @param SupportModifier $support Поддержка.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поддержка чужая.
     */
    private function declareSupport(
        GameRecord $game,
        int $actorUserId,
        array $input,
        array $target,
        GameBattleRecord $battle,
        SupportModifier $support,
    ): array {
        $effect = $this->supportEffect($game, $input, $target, $battle, $support);
        $supporters = $target['supporters'];
        $supporters[] = $this->supporter($input, $effect, $support);
        $saved = $this->saveSupport($input, $battle, $target['strikeId'], $supporters);
        $this->postSupport($game, $actorUserId, $input, $target, $battle, $effect, $support);

        return [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => null,
            'strikeId' => $target['strikeId'],
            'side' => $effect['side'],
            'advantage' => $support->getAdvantage(),
            'spent' => 0,
            'declared' => true,
        ];
    }

    /**
     * Проверяет выбранное оружие поддержки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $target Цель реакции.
     * @param GameBattleRecord $battle Бой.
     * @param SupportModifier $support Поддержка.
     *
     * @return array<string, mixed> Эффект.
     *
     * @throws GameInvalidException Если оружие или мастерство чужие.
     */
    private function supportEffect(
        GameRecord $game,
        array $input,
        array $target,
        GameBattleRecord $battle,
        SupportModifier $support,
    ): array {
        $effect = $this->effects->supportInput($input['context']);
        $this->assertSupportProfile($target, $effect, $support);
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
        $this->assertMastery($game, $quote, $effect, $support);
        $this->ports->perform->assertSpends($game, $quote['document']['sheet'], $quote['spends']);

        return $effect;
    }

    /**
     * Сторона и профиль оружия поддержки.
     *
     * @param array<string, mixed> $target Цель реакции.
     * @param array<string, mixed> $effect Эффект.
     * @param SupportModifier $support Поддержка.
     *
     * @return void
     *
     * @throws GameInvalidException Если сторона или профиль чужие.
     */
    private function assertSupportProfile(array $target, array $effect, SupportModifier $support): void
    {
        if (!in_array($effect['side'], $support->getSides(), true)) {
            throw new GameInvalidException('Game strike support side is invalid');
        }

        $attackProfile = $target['open']['profile_type'] ?? $target['row']['profile_type'] ?? null;
        if ($support->getProfile() !== null && $attackProfile !== $support->getProfile()) {
            throw new GameInvalidException('Game strike support profile is invalid');
        }

        if ($support->getProfile() !== null && $effect['profileType'] !== $support->getProfile()) {
            throw new GameInvalidException('Game strike support weapon profile is invalid');
        }
    }

    /**
     * Сохраняет заявку поддержки.
     *
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Строка.
     * @param list<array<string, mixed>> $supporters Заявившиеся.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия другая.
     */
    private function saveSupport(
        array $input,
        GameBattleRecord $battle,
        int $strikeId,
        array $supporters,
    ): GameBattleRecord {
        return $this->battle->saveStrike($battle, $input, $strikeId, 'supporters', $supporters);
    }

    /**
     * Мастерство оружия не ниже минимума поддержки.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $quote Расчёт.
     * @param array<string, mixed> $effect Эффект.
     * @param SupportModifier $support Поддержка.
     *
     * @return void
     *
     * @throws GameInvalidException Если мастерство низкое.
     */
    private function assertMastery(GameRecord $game, array $quote, array $effect, SupportModifier $support): void
    {
        $mastery = $this->ports->rules->outcome->weaponMasteryOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $quote['document']['sheet'],
            $quote['document']['choices'],
            $effect['itemInventoryId'],
            $effect['itemRuleCode'],
            $effect['profileType'],
        );
        $minMastery = $support->getMinMastery();
        if ($minMastery !== null && $mastery->compare($minMastery) < 0) {
            throw new GameInvalidException('Game strike support mastery is too low');
        }
    }

    /**
     * Запись поддержки в строку.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $effect Эффект.
     * @param SupportModifier $support Поддержка.
     *
     * @return array<string, mixed> Запись.
     */
    private function supporter(array $input, array $effect, SupportModifier $support): array
    {
        return [
            'actor' => $input['actor'],
            'ruleCode' => $input['actionRuleCode'],
            'side' => $effect['side'],
            'advantage' => $support->getAdvantage(),
            'source' => $support->getSource(),
            'weapon' => [
                'itemInventoryId' => $effect['itemInventoryId'],
                'itemRuleCode' => $effect['itemRuleCode'],
                'profileType' => $effect['profileType'],
            ],
            'expectedSheetVersion' => $input['context']['expectedSheetVersion'],
        ];
    }

    /**
     * Сообщение о поддержке.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $target Цель реакции.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $effect Эффект.
     * @param SupportModifier $support Поддержка.
     *
     * @return void
     */
    private function postSupport(
        GameRecord $game,
        int $actorUserId,
        array $input,
        array $target,
        GameBattleRecord $battle,
        array $effect,
        SupportModifier $support,
    ): void {
        $threadId = $this->value->chatId($target);
        $this->ports->chat->postStrikeReaction(
            $game,
            $actorUserId,
            $input['actor']['type'],
            $input['actor']['id'],
            $battle,
            $threadId,
            $input['actionRuleCode'],
            $effect['side'],
            $effect['side'] === 'attacker' ? $target['attacker'] : $target['target'],
            0,
            $support->getAdvantage(),
            $this->value->spellParent($target),
        );
    }

}
