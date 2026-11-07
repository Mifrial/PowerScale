<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong

use Closure;
use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CoverAlly;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ReactionModifier;
use Mifrial\Roleplay\Rule\Spec\AbilityCombat;
use Mifrial\Roleplay\Rule\Spec\SupportSpecs;

/**
 * HTTP-команда не-атакующего действия.
 */
final class GameActionDeclare
{
    private readonly GameBattleRepository $battles;

    private readonly GameActionRepository $actions;

    private readonly GameCommandRepository $commands;

    private readonly GameSessionRepository $sessions;

    private readonly GameStrikeAccess $access;

    private readonly GameReplayTransaction $replay;

    private readonly GameDeliverySignal $delivery;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $gateway Шлюз таблиц.
     * @param IGames $games Игры.
     * @param GameCardAccess $cardAccess Видимость карточки.
     * @param GameActionPerform $perform Общий сценарий действия.
     * @param GamePreparedDefense $prepared Подготовка.
     * @param GameActionChat $chat Сообщение чата.
     * @param GameStrikeSheets $sheets Проекция конфликта листа.
     * @param IEventManager $events События доставки.
     * @param GameProcessFlow $processes Сессия процесса.
     * @param GameStrikeRules $rules Срез.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $gateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameActionPerform $perform,
        private readonly GamePreparedDefense $prepared,
        private readonly GameActionChat $chat,
        private readonly GameStrikeSheets $sheets,
        IEventManager $events,
        private readonly GameProcessFlow $processes,
        private readonly GameStrikeRules $rules,
    ) {
        $this->battles = new GameBattleRepository($gateway);
        $this->actions = new GameActionRepository($gateway);
        $this->commands = new GameCommandRepository($gateway);
        $this->sessions = new GameSessionRepository($gateway);
        $characters = new GameCharacterRepository($gateway);
        $members = new GameMemberRepository($gateway);
        $this->access = new GameStrikeAccess($members, $characters, $this->battles, $cardAccess);
        $this->replay = new GameReplayTransaction($gateway);
        $this->delivery = new GameDeliverySignal($events);
    }

    /**
     * Выполняет команду.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Узкий итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или боя нет.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameBattleConflictException Если версия или ключ чужие.
     */
    public function act(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);
        $key = $body['idempotencyKey'] ?? null;
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null || !is_string($key) || $key === '') {
            throw new GameInvalidException('Game action is invalid');
        }

        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, 'action', $key),
            fn (): int => $this->commands->reserve($gameId, $sessionId, 'action', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->doAct($game, $actorUserId, $editAll, $viewAll, $body),
            $body,
        );
        $this->announce($gameId, 'action', $key, $execution['replay']);

        return $execution['result'];
    }

    /**
     * Объявляет реакцию в открытом ударе.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или боя нет.
     * @throws GameInvalidException Если реакция отвергнута.
     * @throws GameBattleConflictException Если версия боя или листа чужая.
     */
    public function reaction(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);
        $key = $body['idempotencyKey'] ?? null;
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null || !is_string($key) || $key === '') {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, 'reaction', $key),
            fn (): int => $this->commands->reserve($gameId, $sessionId, 'reaction', $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->doReaction($game, $actorUserId, $editAll, $body),
            $body,
        );
        $this->announce($gameId, 'reaction', $key, $execution['replay']);

        return $execution['result'];
    }

    /**
     * Пишет лист, ожидание, подготовку и версию боя.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если актора, цели или боя нет.
     * @throws GameBattleConflictException Если версия боя или листа другая.
     */
    private function doAct(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $input = $this->input($body);
        $process = null;
        if ($input['processRuleCode'] !== null) {
            $battle = $this->battleFor($game, $actorUserId, $editAll, $input, false);
            $process = $this->processes->prepare(
                $game,
                $battle,
                $input['actor'],
                $input['processRuleCode'],
                $input['processStepCode'],
                'action',
            );
            if ($process === null) {
                throw new GameInvalidException('Game process step is invalid');
            }

            $input['actionRuleCode'] = $process['actionCode'];
            $input['processContext'] = $process;
        }

        $followUp = $process === null && $this->perform->requiresFollowUp($game, $input['actionRuleCode']);
        if ($followUp && $input['targetKey'] === null) {
            throw new GameInvalidException('Game action target is missing');
        }

        $battle = $process === null
            ? $this->battleFor($game, $actorUserId, $editAll, $input, $followUp)
            : $this->battleFor($game, $actorUserId, $editAll, $input, false);
        $applied = $this->apply(
            $game,
            $input,
            $battle,
            $actorUserId,
            $viewAll,
            $followUp,
            $process,
        );
        if (!$applied['debt']) {
            $this->postChat($game, $actorUserId, $input, $applied, $battle);
        }

        return $this->result($battle, $input, $applied, $followUp);
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
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если реакция отвергнута.
     * @throws GameBattleConflictException Если версия боя или листа чужая.
     */
    private function doReaction(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $body,
    ): array {
        $input = $this->reactionInput($body);
        $this->access->assertAttacker($game, $actorUserId, $editAll, $input['actor']);
        $sessionId = $this->sessions->findSessionId($game->getId());
        $battle = $this->battleOf($sessionId, $input['battleId'], $input['expectedBattle']);
        $this->access->assertRoster($input['battleId'], $input['actor'], []);

        $open = $this->actions->findOpenWithStrikes($input['battleId'], 'strike');
        $wide = false;
        $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
        if ($open === null || count($strikes) !== 1) {
            $open = $this->actions->findOpenWithStrikes($input['battleId'], 'wide_strike');
            $strikes = is_array($open['strikes'] ?? null) ? $open['strikes'] : [];
            $wide = true;
        }

        $row = $wide
            ? $this->rowOfTarget($strikes, $input['context'])
            : (is_array($strikes[0] ?? null) ? $strikes[0] : null);
        if ($open === null || $row === null) {
            throw new GameInvalidException('Game strike reaction is not available');
        }

        if (($row['reaction'] ?? null) !== null) {
            throw new GameInvalidException('Game strike reaction is already closed');
        }

        $strikeId = $row['id'] ?? null;
        $attacker = $this->strikeParticipant($open['attacker_kind'] ?? null, $open['attacker_id'] ?? null);
        $target = $this->strikeParticipant($row['target_type'] ?? null, $row['target_id'] ?? null);
        if (!is_int($strikeId) || $attacker === null || $target === null) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $rule = $slice->findLive($input['actionRuleCode']);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        $reaction = $spec instanceof AbilityActionSpec ? $spec->getBase()->getReaction() : null;
        if (
            $reaction === null
            || $spec->getOperations() !== []
            || $spec->getBase()->getActionEffects() !== []
        ) {
            throw new GameInvalidException('Game strike reaction card is invalid');
        }

        $role = $input['actor'] === $attacker
            ? 'attacker'
            : ($input['actor'] === $target ? 'defender' : 'third_party');
        if (
            $reaction->getTrigger() !== ReactionModifier::TRIGGER_STRIKE
            || $reaction->getWindow() !== ReactionModifier::WINDOW_BEFORE_DEFENSE
            || !$reaction->allows($role)
        ) {
            throw new GameInvalidException('Game strike reaction actor is invalid');
        }

        $support = SupportSpecs::read($rule->getSpecDocument());
        $cover = AbilityCombat::cover($rule->getSpecDocument());
        if ($support === null && $cover === null) {
            throw new GameInvalidException('Game strike reaction has no executable effect');
        }

        if ($support !== null && $cover !== null) {
            throw new GameInvalidException('Game strike reaction effect is ambiguous');
        }

        $supporters = $this->supportersOf($row);
        $coverers = $this->coverersOf($row);
        foreach (array_merge($supporters, $coverers) as $entry) {
            $actor = $entry['actor'] ?? null;
            if (
                is_array($actor)
                && ($actor['type'] ?? null) === $input['actor']['type']
                && ($actor['id'] ?? null) === $input['actor']['id']
            ) {
                throw new GameInvalidException('Game strike reaction is already declared');
            }
        }

        if ($cover !== null) {
            if ($role !== 'third_party') {
                throw new GameInvalidException('Game strike cover actor is invalid');
            }

            $chatStrikeId = is_int($open['id'] ?? null) ? $open['id'] : $strikeId;

            return $this->declareCover(
                $game,
                $input,
                $cover,
                $battle,
                $row,
                $strikeId,
                ($wide ? 'attack-wide-' : 'attack-') . $chatStrikeId,
                $coverers,
                $actorUserId,
            );
        }

        $effect = $this->supportInput($input['context']);
        if (!in_array($effect['side'], $support->getSides(), true)) {
            throw new GameInvalidException('Game strike support side is invalid');
        }

        $attackProfile = $open['profile_type'] ?? $row['profile_type'] ?? null;
        if ($support->getProfile() !== null && $attackProfile !== $support->getProfile()) {
            throw new GameInvalidException('Game strike support profile is invalid');
        }

        if ($support->getProfile() !== null && $effect['profileType'] !== $support->getProfile()) {
            throw new GameInvalidException('Game strike support weapon profile is invalid');
        }

        $quote = $this->perform->quote(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $battle->getPendingActionEffects(),
            false,
            [],
            null,
        );
        $mastery = $this->rules->outcome->weaponMasteryOf(
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

        $this->perform->assertSpends($game, $quote['document']['sheet'], $quote['spends']);

        $supporters[] = [
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
        $this->actions->answerStrike($strikeId, ['supporters' => $supporters]);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $input['expectedBattle'],
            null,
            null,
            null,
            null,
        );
        $chatStrikeId = is_int($open['id'] ?? null) ? $open['id'] : $strikeId;
        $this->chat->postStrikeReaction(
            $game,
            $actorUserId,
            $input['actor']['type'],
            $input['actor']['id'],
            $battle,
            ($wide ? 'attack-wide-' : 'attack-') . $chatStrikeId,
            $input['actionRuleCode'],
            $effect['side'],
            $effect['side'] === 'attacker' ? $attacker : $target,
            0,
            $support->getAdvantage(),
        );

        return [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => null,
            'strikeId' => $strikeId,
            'side' => $effect['side'],
            'advantage' => $support->getAdvantage(),
            'spent' => 0,
            'declared' => true,
        ];
    }

    /**
     * Разбирает тело реакции. Общая часть не знает, какой эффект исполняется.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array{
     *     battleId: int,
     *     expectedBattle: int,
     *     actionRuleCode: string,
     *     context: array<string, mixed>,
     *     actor: array{type: string, id: int}
     * } Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function reactionInput(array $body): array
    {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedVersion'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $context = $body['context'] ?? null;
        if (
            !is_int($battleId)
            || !is_int($expectedBattle)
            || !is_string($actionRuleCode)
            || $actionRuleCode === ''
            || !is_array($context)
            || !is_int($context['expectedSheetVersion'] ?? null)
        ) {
            throw new GameInvalidException('Game strike reaction is invalid');
        }

        return [
            'battleId' => $battleId,
            'expectedBattle' => $expectedBattle,
            'actionRuleCode' => $actionRuleCode,
            'context' => $context,
            'actor' => $this->pair($body['actor'] ?? null),
        ];
    }

    /**
     * Payload эффекта support: сторона и выбранное оружие.
     *
     * @param array<string, mixed> $context Контекст реакции.
     *
     * @return array{side: string, itemInventoryId: int, itemRuleCode: string, profileType: string} Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function supportInput(array $context): array
    {
        $support = is_array($context['support'] ?? null) ? $context['support'] : null;
        $side = is_array($support) ? ($support['side'] ?? null) : null;
        $itemInventoryId = is_array($support) ? ($support['itemInventoryId'] ?? null) : null;
        $itemRuleCode = is_array($support) ? ($support['itemRuleCode'] ?? null) : null;
        $profileType = is_array($support) ? ($support['profileType'] ?? null) : null;
        if (
            ($side !== 'attacker' && $side !== 'defender')
            || !is_int($itemInventoryId)
            || $itemInventoryId < 1
            || !is_string($itemRuleCode)
            || $itemRuleCode === ''
            || ($profileType !== 'strike' && $profileType !== 'throw' && $profileType !== 'shoot')
        ) {
            throw new GameInvalidException('Game strike support is invalid');
        }

        return [
            'side' => $side,
            'itemInventoryId' => $itemInventoryId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
        ];
    }

    /**
     * Строка широкого удара по выбранной цели.
     *
     * @param list<array<string, mixed>> $strikes Строки удара.
     * @param array<string, mixed> $context Контекст реакции.
     *
     * @return array<string, mixed>|null Строка или null.
     */
    private function rowOfTarget(array $strikes, array $context): ?array
    {
        $target = is_array($context['target'] ?? null) ? $context['target'] : null;
        if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
            return null;
        }

        foreach ($strikes as $row) {
            if (
                ($row['target_type'] ?? null) === $target['type']
                && ($row['target_id'] ?? null) === $target['id']
            ) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Уже объявленные поддержки строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return list<array<string, mixed>> Записи.
     */
    private function supportersOf(array $row): array
    {
        return $this->jsonRows($row, 'supporters');
    }

    /**
     * Уже объявленные прикрывающие строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return list<array<string, mixed>> Записи.
     */
    private function coverersOf(array $row): array
    {
        return $this->jsonRows($row, 'coverers');
    }

    /**
     * Список JSON-записей строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return list<array<string, mixed>> Записи.
     */
    private function jsonRows(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Payload эффекта cover_ally: выбранная защита и предмет блока.
     *
     * @param array<string, mixed> $context Контекст реакции.
     *
     * @return array{
     *     reaction: string,
     *     blockItemInventoryId: int,
     *     blockItemProfileIndex: int,
     *     blockItemRuleCode: string
     * } Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function coverInput(array $context): array
    {
        $cover = is_array($context['cover'] ?? null) ? $context['cover'] : null;
        $reaction = is_array($cover) ? ($cover['reaction'] ?? null) : null;
        $inventoryId = is_array($cover) ? ($cover['blockItemInventoryId'] ?? null) : null;
        $profileIndex = is_array($cover) ? ($cover['blockItemProfileIndex'] ?? null) : null;
        $ruleCode = is_array($cover) ? ($cover['blockItemRuleCode'] ?? null) : null;
        if (
            !is_string($reaction)
            || $reaction === ''
            || !is_int($inventoryId)
            || !is_int($profileIndex)
            || !is_string($ruleCode)
        ) {
            throw new GameInvalidException('Game strike cover is invalid');
        }

        return [
            'reaction' => $reaction,
            'blockItemInventoryId' => $inventoryId,
            'blockItemProfileIndex' => $profileIndex,
            'blockItemRuleCode' => $ruleCode,
        ];
    }

    /**
     * Объявляет прикрытие: заявка без списания.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход реакции.
     * @param CoverAlly $cover Узел прикрытия.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка удара.
     * @param int $strikeId Строка.
     * @param string $threadId Нить атаки.
     * @param list<array<string, mixed>> $coverers Уже заявившиеся.
     * @param int $actorUserId Пользователь.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если защита недоступна или цена выше прикрытия.
     */
    private function declareCover(
        GameRecord $game,
        array $input,
        CoverAlly $cover,
        GameBattleRecord $battle,
        array $row,
        int $strikeId,
        string $threadId,
        array $coverers,
        int $actorUserId,
    ): array {
        $effect = $this->coverInput($input['context']);
        if (!in_array($effect['reaction'], $cover->getReactions(), true)) {
            throw new GameInvalidException('Game strike cover reaction is invalid');
        }

        $target = $this->strikeParticipant($row['target_type'] ?? null, $row['target_id'] ?? null);
        if ($target === null) {
            throw new GameInvalidException('Game strike cover target is invalid');
        }

        $choices = new GameStrikeTargetChoices($this->rules, $this->sheets);
        $actorChoices = $choices->targetChoices($game, $input['actor']);
        $actorSheet = $this->sheets->getTargetSheet($game, $input['actor']);
        if ($effect['reaction'] === 'block') {
            $this->rules->checks->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $actorChoices,
                $effect['blockItemInventoryId'],
                $effect['blockItemProfileIndex'],
                $effect['blockItemRuleCode'],
            );
        }

        $quote = $this->perform->quote(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $battle->getPendingActionEffects(),
            false,
            [],
            null,
        );
        $coverCost = $this->spentOf($quote['spends']);
        $this->perform->assertSpends($game, $quote['document']['sheet'], $quote['spends']);
        $reactionSpends = $this->rules->spends->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $effect['reaction'],
            $effect['reaction'] === 'block' ? $effect['blockItemInventoryId'] : null,
            $effect['blockItemRuleCode'],
            $actorChoices,
            $actorSheet,
        );
        if ($this->spentOf($reactionSpends) > $coverCost) {
            throw new GameInvalidException('Game strike cover reaction is too expensive');
        }

        $coverers[] = [
            'actor' => $input['actor'],
            'ruleCode' => $input['actionRuleCode'],
            'reaction' => $effect['reaction'],
            'blockItemInventoryId' => $effect['blockItemInventoryId'],
            'blockItemProfileIndex' => $effect['blockItemProfileIndex'],
            'blockItemRuleCode' => $effect['blockItemRuleCode'],
            'expectedSheetVersion' => $input['context']['expectedSheetVersion'],
            'cost' => $coverCost,
            'reactedAt' => $strikeId,
        ];
        $this->actions->answerStrike($strikeId, ['coverers' => $coverers]);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $input['expectedBattle'],
            null,
            null,
            null,
            null,
        );
        $this->chat->postStrikeCover(
            $game,
            $actorUserId,
            $input['actor']['type'],
            $input['actor']['id'],
            $battle,
            $threadId,
            $input['actionRuleCode'],
            $target,
            $effect['reaction'],
            $effect['blockItemRuleCode'],
            $coverCost,
        );

        return [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => null,
            'strikeId' => $strikeId,
            'cover' => $effect['reaction'],
            'spent' => 0,
            'declared' => true,
        ];
    }

    /**
     * Пара участника удара из колонок.
     *
     * @param mixed $type Вид.
     * @param mixed $id Идентификатор.
     *
     * @return array{type: string, id: int}|null Пара или null.
     */
    private function strikeParticipant(mixed $type, mixed $id): ?array
    {
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Пишет сообщение о действии в тред текущего хода.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $applied Итог действия.
     * @param GameBattleRecord $battle Бой.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат недоступен.
     */
    private function postChat(
        GameRecord $game,
        int $actorUserId,
        array $input,
        array $applied,
        GameBattleRecord $battle,
    ): void {
        $process = $input['processContext'] ?? null;
        $step = is_array($process) ? ($process['step'] ?? null) : null;
        $descriptive = $step instanceof ProcessStep && $step->getExecution()->isNone();
        $ruleCode = $descriptive
            ? (string) $process['processRuleCode']
            : (string) $input['actionRuleCode'];

        $this->chat->post(
            $game,
            $actorUserId,
            $input['actor']['type'],
            $input['actor']['id'],
            $ruleCode,
            $applied['performed']['spent'],
            $battle->getId(),
            $battle->getRound(),
            $battle->getActiveIndex(),
            $applied['performed']['rolls'] ?? [],
            $descriptive ? $step->getName() : null,
        );
    }

    /**
     * Узкий итог команды.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $applied Итог действия.
     * @param bool $followUp Нужна ли подготовка после прошлого удара.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(
        GameBattleRecord $battle,
        array $input,
        array $applied,
        bool $followUp,
    ): array {
        if ($applied['debt'] === true) {
            return [
                'battleId' => $battle->getId(),
                'version' => $applied['version'],
                'spent' => $applied['performed']['spent'],
                'sheetVersion' => $applied['performed']['sheetVersion'],
                'targetKey' => null,
                'reaction' => null,
                'pendingActionEffects' => $applied['pending'],
                'rolls' => [],
                'opened' => false,
                'debt' => true,
                'remaining' => $applied['remaining'] ?? 0,
            ];
        }

        return [
            'battleId' => $battle->getId(),
            'version' => $applied['version'],
            'spent' => $applied['performed']['spent'],
            'sheetVersion' => $applied['performed']['sheetVersion'],
            'targetKey' => $followUp ? $input['targetKey'] : null,
            'reaction' => $applied['reaction'],
            'pendingActionEffects' => $applied['pending'],
            'rolls' => $applied['performed']['rolls'] ?? [],
            'opened' => true,
        ];
    }

    /**
     * Выполняет действие и пишет подготовку.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     * @param bool $followUp Нужна ли подготовка после прошлого удара.
     *
     * @return array{
     *     debt: bool,
     *     pending: array<string, mixed>,
     *     performed: array<string, mixed>,
     *     version: int,
     *     reaction: string|null,
     *     remaining?: int
     * } Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа или боя другая.
     */
    private function apply(
        GameRecord $game,
        array $input,
        GameBattleRecord $battle,
        int $actorUserId,
        bool $viewAll,
        bool $followUp,
        ?array $process,
        bool $paid = false,
    ): array {
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $step = null;
        if (is_array($process) && ($process['step'] ?? null) instanceof ProcessStep) {
            $step = $process['step'];
        }

        $descriptive = $step !== null && $step->getExecution()->isNone();
        $quoted = null;
        $priced = null;
        if ($descriptive) {
            $quoted = $this->perform->quoteStep(
                $game,
                $input['actor']['type'],
                $input['actor']['id'],
                $step,
            );
            $priced = [
                'pending' => $battle->getPendingActionEffects(),
                'amounts' => $this->amountsOf($quoted['spends']),
            ];
        }

        if (!$paid) {
            $debt = new GameActionDebt();
            $debtKey = $debt->key($input['actor']['type'], $input['actor']['id']);
            $debt->assertFree($battle->getActionDebt(), $debtKey);
            if (!$descriptive) {
                $quote = $this->perform->quote(
                    $game,
                    $input['actor']['type'],
                    $input['actor']['id'],
                    $input['actionRuleCode'],
                    $battle->getPendingActionEffects(),
                    !$followUp,
                    $input['chosenAmounts'],
                    $step,
                );
                $quoted = $quote;
                $priced = $quote['priced'];
            }

            $planned = $debt->plan(
                $slice,
                $quoted['document']['sheet'],
                $quoted['spends'],
                $debt->refillCode($slice),
                'action',
                [
                    'battleId' => $input['battleId'],
                    'expectedVersion' => $input['expectedBattle'],
                    'expectedSheetVersion' => $input['expectedSheet'],
                    'actionRuleCode' => $input['actionRuleCode'] ?? $input['processRuleCode'] ?? '',
                    'followUpTargetKey' => $input['targetKey'],
                    'chosenAmounts' => $input['chosenAmounts'],
                    'actor' => $input['actor'],
                    'processRuleCode' => $input['processRuleCode'],
                    'processStepCode' => $input['processStepCode'],
                ],
            );
            if (!$planned['open']) {
                $sheetVersion = $this->perform->spend(
                    $game,
                    $input['actor']['type'],
                    $input['actor']['id'],
                    $input['expectedSheet'],
                    $planned['spends'],
                );
                $debtMap = $battle->getActionDebt();
                $debtMap[$debtKey] = $planned['slot'];
                $saved = $this->battles->replaceTracked(
                    $battle->getId(),
                    $input['expectedBattle'],
                    $priced['pending'],
                    $debtMap,
                    null,
                    null,
                );

                return [
                    'debt' => true,
                    'pending' => $priced['pending'],
                    'performed' => [
                        'pending' => $priced['pending'],
                        'sheetVersion' => $sheetVersion,
                        'spent' => $this->spentOf($planned['spends']),
                        'amounts' => $priced['amounts'],
                        'rolls' => [],
                    ],
                    'version' => $saved->getStateVersion(),
                    'reaction' => null,
                    'remaining' => $planned['slot']['remaining'] ?? 0,
                ];
            }
        }

        if ($descriptive) {
            $spends = $paid ? $quoted['spends'] : $planned['spends'];
            if ($paid) {
                $sheetVersion = $input['expectedSheet'];
            } else {
                $this->perform->assertSpends($game, $quoted['document']['sheet'], $spends);
                $sheetVersion = $this->perform->spend(
                    $game,
                    $input['actor']['type'],
                    $input['actor']['id'],
                    $input['expectedSheet'],
                    $spends,
                );
            }

            $processId = $this->processes->begin($battle, $input['actor'], $process);
            $pending = $this->processes->complete(
                $game,
                $input['actor'],
                $processId,
                $step->getCode(),
                true,
                $battle->getPendingActionEffects(),
            );
            $stored = $this->store($input, $pending, null);

            return [
                'debt' => false,
                'pending' => $stored['pending'],
                'performed' => [
                    'pending' => $stored['pending'],
                    'sheetVersion' => $sheetVersion,
                    'spent' => $this->spentOf($spends),
                    'amounts' => $priced['amounts'],
                    'rolls' => [],
                ],
                'version' => $stored['version'],
                'reaction' => null,
            ];
        }

        $reaction = $followUp
            ? $this->perform->followUpReaction(
                $game,
                $input['actionRuleCode'],
                $battle->getPendingActionEffects(),
                $input['actor']['type'],
                $input['actor']['id'],
                (string) $input['targetKey'],
            )
            : null;
        $performed = $this->performAction(
            $game,
            $input,
            $battle->getPendingActionEffects(),
            $actorUserId,
            $viewAll,
            !$followUp,
            $step,
            $paid,
        );
        if (is_array($process)) {
            $processId = $this->processes->begin($battle, $input['actor'], $process);
            $performed['pending'] = $this->processes->complete(
                $game,
                $input['actor'],
                $processId,
                $process['step']->getCode(),
                true,
                $performed['pending'],
            );
        }

        $stored = $this->store($input, $performed['pending'], $reaction);

        return [
            'debt' => false,
            'pending' => $stored['pending'],
            'performed' => $performed,
            'version' => $stored['version'],
            'reaction' => $reaction,
        ];
    }

    /**
     * Сохраняет ожидание и, если нужно, подготовку.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $reaction Реакция цели или null для обычного действия.
     *
     * @return array{pending: array<string, mixed>, version: int} Итог.
     *
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function store(array $input, array $pending, ?string $reaction): array
    {
        if ($reaction !== null) {
            $pending = $this->prepared->replace(
                $pending,
                $input['actor']['type'],
                $input['actor']['id'],
                (string) $input['targetKey'],
                $reaction,
                $input['actionRuleCode'],
            );
        }

        $battle = $this->battles->replacePending($input['battleId'], $input['expectedBattle'], $pending);

        return ['pending' => $pending, 'version' => $battle->getStateVersion()];
    }

    /**
     * Выполняет общий сценарий действия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $pending Ожидание.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     *
     * @return array<string, mixed> Итог сценария.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    private function performAction(
        GameRecord $game,
        array $input,
        array $pending,
        int $actorUserId,
        bool $viewAll,
        bool $allowZeroChosen,
        ?ProcessStep $step = null,
        bool $paid = false,
    ): array {
        return $this->perform->perform(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $input['expectedSheet'],
            $pending,
            $allowZeroChosen,
            $input['chosenAmounts'],
            $this->conflictProjection(
                $game,
                ['type' => $input['actor']['type'], 'id' => $input['actor']['id']],
                $actorUserId,
                $viewAll,
            ),
            $step,
            $paid,
        );
    }

    /**
     * Исполняет действие после доплаты долга.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Сохранённое объявление.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа после доплаты.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     * @throws GameNotFoundException Если игры или боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    public function resume(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        int $expectedBattle,
        int $expectedSheet,
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);
        $input = $this->input($body);
        $input['expectedBattle'] = $expectedBattle;
        $input['expectedSheet'] = $expectedSheet;
        $process = null;
        if ($input['processRuleCode'] !== null) {
            $battle = $this->battleFor($game, $actorUserId, $editAll, $input, false);
            $process = $this->processes->prepare(
                $game,
                $battle,
                $input['actor'],
                $input['processRuleCode'],
                $input['processStepCode'],
                'action',
            );
            if ($process === null) {
                throw new GameInvalidException('Game process step is invalid');
            }

            $input['actionRuleCode'] = $process['actionCode'];
            $input['processContext'] = $process;
        }

        $followUp = $process === null && $this->perform->requiresFollowUp($game, $input['actionRuleCode']);
        $battle = $this->battleFor($game, $actorUserId, $editAll, $input, $followUp);
        $applied = $this->apply(
            $game,
            $input,
            $battle,
            $actorUserId,
            $viewAll,
            $followUp,
            $process,
            true,
        );
        $this->postChat($game, $actorUserId, $input, $applied, $battle);

        return array_merge(
            $this->result($battle, $input, $applied, $followUp),
            [
                'kind' => 'action',
                'phase' => 'declare',
                'battleId' => $battle->getId(),
            ],
        );
    }

    /**
     * Суммы трат по ресурсам.
     *
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     *
     * @return array<string, int> Суммы.
     */
    private function amountsOf(array $spends): array
    {
        $amounts = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                continue;
            }

            $code = $spend->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $native;
        }

        return $amounts;
    }

    /**
     * Сумма ОД в тратах.
     *
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     *
     * @return int Сумма.
     */
    private function spentOf(array $spends): int
    {
        $sum = 0;
        foreach ($spends as $spend) {
            if ($spend->getResourceCode() !== 'action-points') {
                continue;
            }

            $native = $spend->getAmount()->toNative();
            if (is_int($native)) {
                $sum += $native;
            }
        }

        return $sum;
    }

    /**
     * Разбирает тело команды.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return array{
     *     battleId: int,
     *     expectedBattle: int,
     *     expectedSheet: int,
     *     actionRuleCode: string,
     *     targetKey: string|null,
     *     chosenAmounts: array<string, int>,
     *     processRuleCode: string|null,
     *     processStepCode: string|null,
     *     actor: array{type: string, id: int}
     * } Вход.
     *
     * @throws GameInvalidException Если поле чужое.
     */
    private function input(array $body): array
    {
        $battleId = $body['battleId'] ?? null;
        $expectedBattle = $body['expectedVersion'] ?? null;
        $expectedSheet = $body['expectedSheetVersion'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $targetKey = $body['followUpTargetKey'] ?? null;
        $chosenAmounts = $this->chosenAmounts($body['chosenAmounts'] ?? []);
        $actor = $this->pair($body['actor'] ?? null);
        $processRuleCode = $body['processRuleCode'] ?? null;
        $processStepCode = $body['processStepCode'] ?? null;
        if (!is_int($battleId) || !is_int($expectedBattle) || !is_int($expectedSheet)) {
            throw new GameInvalidException('Game action is invalid');
        }

        if (
            !is_string($actionRuleCode)
            || ($targetKey !== null && !is_string($targetKey))
            || ($processRuleCode !== null && (!is_string($processRuleCode) || $processRuleCode === ''))
            || ($processStepCode !== null && (!is_string($processStepCode) || $processStepCode === ''))
            || ($processRuleCode === null && $processStepCode !== null)
        ) {
            throw new GameInvalidException('Game action is invalid');
        }

        return [
            'battleId' => $battleId,
            'expectedBattle' => $expectedBattle,
            'expectedSheet' => $expectedSheet,
            'actionRuleCode' => $actionRuleCode,
            'targetKey' => $targetKey,
            'chosenAmounts' => $chosenAmounts,
            'actor' => $actor,
            'processRuleCode' => $processRuleCode,
            'processStepCode' => $processStepCode,
        ];
    }

    /**
     * Проверяет карту выбранных сумм.
     *
     * @param mixed $value Значение из JSON.
     *
     * @return array<string, int> Суммы по ресурсам.
     *
     * @throws GameInvalidException Если карта чужая.
     */
    private function chosenAmounts(mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new GameInvalidException('Game action chosen amounts are invalid');
        }

        $amounts = [];
        foreach ($value as $code => $amount) {
            $this->assertChosenAmount($code, $amount);
            $amounts[$code] = $amount;
        }

        return $amounts;
    }

    /**
     * Проверяет одну выбранную сумму.
     *
     * @param mixed $code Код ресурса.
     * @param mixed $amount Сумма.
     *
     * @return void
     *
     * @throws GameInvalidException Если запись чужая.
     */
    private function assertChosenAmount(mixed $code, mixed $amount): void
    {
        if (!is_string($code) || $code === '' || !is_int($amount) || $amount < 0) {
            throw new GameInvalidException('Game action chosen amounts are invalid');
        }
    }

    /**
     * Проверяет доступ, бой, цель и отсутствие открытого действия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $input Вход.
     * @param bool $followUp Нужна ли цель прошлого удара.
     *
     * @return GameBattleRecord Бой.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameInvalidException Если действие уже открыто.
     * @throws GameNotFoundException Если боя или цели нет.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    private function battleFor(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        array $input,
        bool $followUp,
    ): GameBattleRecord {
        $this->access->assertAttacker($game, $actorUserId, $editAll, $input['actor']);
        $sessionId = $this->sessions->findSessionId($game->getId());
        $battle = $this->battleOf($sessionId, $input['battleId'], $input['expectedBattle']);
        if ($this->actions->findOpenAny($input['battleId']) !== null) {
            throw new GameInvalidException('Game action is already open');
        }

        $targets = $followUp ? [$this->targetOfKey((string) $input['targetKey'])] : [];
        $this->access->assertRoster($input['battleId'], $input['actor'], $targets);

        return $battle;
    }

    /**
     * Бой текущей сессии с проверкой версии.
     *
     * @param int|null $sessionId Сессия.
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameBattleRecord Строка боя.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия другая.
     */
    private function battleOf(?int $sessionId, int $battleId, int $expectedVersion): GameBattleRecord
    {
        $battle = $this->battles->find($battleId);
        if ($battle === null || $sessionId === null || $battle->getSessionId() !== $sessionId) {
            throw new GameNotFoundException('Game action battle was not found');
        }

        if ($battle->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }

    /**
     * Пара type/id из JSON.
     *
     * @param mixed $pair Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function pair(mixed $pair): array
    {
        $type = is_array($pair) ? ($pair['type'] ?? null) : null;
        $id = is_array($pair) ? ($pair['id'] ?? null) : null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game action actor is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Пара type/id из ключа цели.
     *
     * @param string $key Ключ character:N или npc:N.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function targetOfKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        $type = $parts[0];
        $rawId = $parts[1] ?? null;
        $id = is_string($rawId) && ctype_digit($rawId) ? (int) $rawId : 0;
        if (($type !== 'character' && $type !== 'npc') || $id < 1) {
            throw new GameInvalidException('Game action target is invalid');
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Проектор конфликта листа актора.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $actor Актор.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     *
     * @return Closure(
     *     CharacterConflictException|GameEconomyConflictException
     * ): array{choices: array<string, mixed>, sheet: array<string, mixed>} Проектор.
     */
    private function conflictProjection(
        GameRecord $game,
        array $actor,
        int $actorUserId,
        bool $viewAll,
    ): Closure {
        return fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->sheets
            ->conflicts->projectWriteConflict($game, $actor, $exception, $actorUserId, $viewAll);
    }

    /**
     * Шлёт сигнал после первой записи.
     *
     * @param int $gameId Игра.
     * @param string $kind Вид команды action или reaction.
     * @param string $idempotencyKey Ключ.
     * @param bool $replay Повтор.
     *
     * @return void
     */
    private function announce(int $gameId, string $kind, string $idempotencyKey, bool $replay): void
    {
        if ($replay) {
            return;
        }

        $stored = $this->commands->findByKey($gameId, $kind, $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded($gameId, $kind, $stored['id'], []);
        }
    }
}
