<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IBluntUnconscious;
use Mifrial\Roleplay\Mechanic\Interface\IDamageAsWounds;
use Mifrial\Roleplay\Mechanic\Interface\IExhaustionAmount;
use Mifrial\Roleplay\Mechanic\Interface\IExhaustionWound;
use Mifrial\Roleplay\Mechanic\Interface\IInjuryEfficiency;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Операции immediate consequences и чат close. Формулу injury не считает.
 */
final class GameStrikeConsequences
{
    /**
     * @param GameStrikeRules $rules Срез удара.
     * @param GameCheckRoll $checks Бросок с заданной трудностью.
     * @param ICharacterRuleSlices $slices Срез ревизии.
     * @param ICharacterFormulaContexts $contexts Лист.
     * @param IMechanics $mechanics Каталог.
     * @param IMechanicEngine $engine Резолв хендлеров.
     * @param IChats $chats Чат игры.
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameCheckRoll $checks,
        private readonly ICharacterRuleSlices $slices,
        private readonly ICharacterFormulaContexts $contexts,
        private readonly IMechanics $mechanics,
        private readonly IMechanicEngine $engine,
        private readonly IChats $chats,
    ) {
    }

    /**
     * Список операций цели и узкие исходы бросков.
     *
     * @param array<string, mixed> $sheet Лист цели.
     * @param array{kind: string, remainder: array{base: int, size: int}, quotient: int} $split Деление обычного урона.
     *
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>}
     */
    public function effects(
        GameRecord $game,
        array $sheet,
        DimensionalNumber $injury,
        int $success,
        array $split,
        ?string $damageTypeCode,
        bool $knockout = true,
        ?DimensionalNumber $endurance = null,
    ): array {
        $slice = $this->slices->get($game->getSpaceId(), $game->getRulesRevision());
        $hooks = $damageTypeCode === null ? [] : $this->hooks($slice, $damageTypeCode);
        $cutting = $this->cutting($hooks);
        $quotient = $split['quotient'];
        $operations = $cutting ? [] : [$split];
        $wound = 0;
        if ($cutting !== null) {
            $wound = $this->woundStrength($slice, $sheet, $injury);
            if ($wound > 0) {
                $operations[] = [
                    'kind' => 'putState',
                    'stateRuleCode' => $cutting,
                    'value' => $wound,
                    'bandage' => 0,
                    'clotting' => 0,
                    'internal' => false,
                    'aided' => false,
                    'heldBy' => null,
                ];
            }
        }

        foreach ($hooks as $hook) {
            $amount = $this->exhaustionAmount($hook['handler'], $hook['payload'], $quotient);
            if ($amount === null || $amount === 0) {
                continue;
            }

            $operations[] = [
                'kind' => 'putState',
                'stateRuleCode' => $this->stateCode($hook['payload']),
                'value' => $amount,
            ];
        }

        $consequence = ['wound' => $wound, 'exhaustionDelta' => $cutting ? 0 : $quotient];
        if ($endurance instanceof DimensionalNumber) {
            $consequence['endurance'] = ['base' => $endurance->getBase(), 'size' => $endurance->getSize()];
        }
        $wasUnconscious = $this->hasFlag($slice, $sheet, 'unconscious');
        if (!$cutting && $quotient !== 0 && !$wasUnconscious) {
            $will = $this->will($game, $slice, $sheet, $quotient);
            $consequence['will'] = $will['roll'];
            $consequence['willOutcome'] = $will['outcome'];
            foreach ($this->declineSheetOps($sheet, $slice, $will['operation']) as $operation) {
                $operations[] = $operation;
            }
        }

        if ($knockout && ($will['outcome'] ?? null) !== 'unconscious' && $this->blunt($hooks, $success, $injury, $slice, $sheet, $endurance)) {
            $code = $this->singleFlag($slice, 'unconscious');
            foreach ($this->declineSheetOps($sheet, $slice, ['kind' => 'replaceDecline', 'stateRuleCode' => $code]) as $operation) {
                $operations[] = $operation;
            }
            $consequence['unconscious'] = true;
        }

        $delta = $consequence['exhaustionDelta'];
        if ($delta !== 0 || $wound > 0) {
            $maim = $this->maim($game, $slice, $sheet, max($delta, intdiv($wound, 2)), $hooks);
            $strength = is_int($maim['rating'] ?? null) && $maim['rating'] < 0 ? -$maim['rating'] : 0;
            $consequence['maim'] = $maim;
            if ($strength > 0) {
                $operations[] = [
                    'kind' => 'putState',
                    'stateRuleCode' => $this->singleFlag($slice, 'maim'),
                    'value' => $strength,
                    'maim' => $this->maimSidecar($strength),
                ];
            }
        }

        return ['operations' => $operations, 'consequence' => $consequence];
    }

    /**
     * Воля при изменении истощения. Уже без сознания — пустой итог.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{roll: array<string, mixed>|null, operation: array<string, mixed>|null, outcome: string|null}
     */
    public function willForDelta(GameRecord $game, array $sheet, int $delta): array
    {
        $slice = $this->slices->get($game->getSpaceId(), $game->getRulesRevision());
        if ($delta === 0 || $this->hasFlag($slice, $sheet, 'unconscious')) {
            return ['roll' => null, 'operation' => null, 'outcome' => null];
        }

        return $this->will($game, $slice, $sheet, $delta);
    }

    /**
     * Бросок увечья с уже посчитанной трудностью.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int}
     */
    public function maimRoll(GameRecord $game, array $sheet, int $difficulty): array
    {
        $slice = $this->slices->get($game->getSpaceId(), $game->getRulesRevision());

        return $this->maim($game, $slice, $sheet, $difficulty);
    }

    /**
     * Код единственной карточки с флагом.
     */
    public function singleState(GameRecord $game, string $flag): string
    {
        $slice = $this->slices->get($game->getSpaceId(), $game->getRulesRevision());

        return $this->singleFlag($slice, $flag);
    }

    /**
     * Пишет цепочку в чат игры: действие, нарратив, бросок, итог, Воля, увечье.
     *
     * @param array{base: int, size: int}|array<string, mixed> $hitRoll Бросок попадания.
     * @param array<string, mixed> $calc Payload attack-calc.
     * @param array{roll: array<string, mixed>, text: string}|null $will Проверка истощения.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param string|null $parentId Родительский ход.
     *
     * @return void
     */
    public function post(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        bool $announce,
        string $action,
        string $strike,
        array $hitRoll,
        string $result,
        array $calc,
        ?array $will = null,
        ?array $maim = null,
        ?array $speaker = null,
        ?array $defenseRoll = null,
        ?string $parentId = null,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is invalid');
        }

        $thread = $this->attackThread($threadId, $parentId);
        try {
            if ($announce) {
                $this->sendText($chatId, $actorUserId, $action, $thread, $speaker);
                $this->sendText($chatId, $actorUserId, $strike, $thread, $speaker);
            }

            $rolls = [$hitRoll];
            if ($defenseRoll !== null) {
                $rolls[] = $defenseRoll;
            }
            $this->sendRolls($chatId, $actorUserId, $rolls, $thread, $speaker);
            $this->sendOutcome($chatId, $actorUserId, $result, $calc, $will, $maim, $thread, $speaker);
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * @param list<string> $lines Тексты.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     */
    public function postLines(GameRecord $game, int $actorUserId, string $threadId, array $lines, ?array $speaker = null, ?string $parentId = null): void
    {
        $chatId = $this->chatId($game);
        $thread = $this->attackThread($threadId, $parentId);
        try {
            foreach ($lines as $line) {
                $this->sendText($chatId, $actorUserId, $line, $thread, $speaker);
            }
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * @param list<array<string, mixed>> $rolls Броски одной строкой.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     */
    public function postRolls(GameRecord $game, int $actorUserId, string $threadId, array $rolls, ?array $speaker = null, ?string $parentId = null): void
    {
        $chatId = $this->chatId($game);
        try {
            $this->sendRolls($chatId, $actorUserId, $rolls, $this->attackThread($threadId, $parentId), $speaker);
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * @param array<string, mixed> $calc Payload attack-calc.
     * @param array{roll: array<string, mixed>, text: string}|null $will Проверка истощения.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     */
    public function postOutcome(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        string $result,
        array $calc,
        ?array $will = null,
        ?array $maim = null,
        ?array $speaker = null,
        ?string $parentId = null,
    ): void {
        $chatId = $this->chatId($game);
        try {
            $this->sendOutcome(
                $chatId,
                $actorUserId,
                $result,
                $calc,
                $will,
                $maim,
                $this->attackThread($threadId, $parentId),
                $speaker,
            );
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    private function chatId(GameRecord $game): int
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is invalid');
        }

        return $chatId;
    }

    /**
     * Тред атаки с родительским ходом.
     *
     * @param string $threadId Идентификатор атаки.
     * @param string|null $parentId Родительский ход.
     *
     * @return array{id: string, kind: string, parentId?: string} Тред.
     */
    private function attackThread(string $threadId, ?string $parentId): array
    {
        $thread = ['id' => $threadId, 'kind' => 'attack'];
        if ($parentId !== null) {
            $thread['parentId'] = $parentId;
        }

        return $thread;
    }

    /**
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     */
    private function sendText(int $chatId, int $actorUserId, string $text, array $thread, ?array $speaker): void
    {
        $this->chats->send($chatId, $actorUserId, $text, $this->withThread([], $thread, $speaker), null);
    }

    /**
     * @param list<array<string, mixed>> $rolls Броски.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     */
    private function sendRolls(int $chatId, int $actorUserId, array $rolls, array $thread, ?array $speaker): void
    {
        $attachments = [];
        foreach ($rolls as $roll) {
            $attachments[] = ['type' => 'roll', 'payload' => $roll];
        }
        $this->chats->send($chatId, $actorUserId, '', $this->withThread($attachments, $thread, $speaker), null);
    }

    /**
     * @param array<string, mixed> $calc Payload.
     * @param array{roll: array<string, mixed>, text: string}|null $will Воля.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     */
    private function sendOutcome(
        int $chatId,
        int $actorUserId,
        string $result,
        array $calc,
        ?array $will,
        ?array $maim,
        array $thread,
        ?array $speaker,
    ): void {
        $this->chats->send($chatId, $actorUserId, $result, $this->withThread([[
            'type' => 'attack-calc',
            'payload' => $calc,
        ],
        ], $thread, $speaker), null);
        if ($will !== null) {
            $this->chats->send($chatId, $actorUserId, '', $this->withThread([['type' => 'roll', 'payload' => $will['roll']]], $thread, $speaker), null);
            $this->chats->send($chatId, $actorUserId, $will['text'], $this->withThread([], $thread, $speaker), null);
        }
        if ($maim !== null) {
            $this->chats->send($chatId, $actorUserId, '', $this->withThread([['type' => 'roll', 'payload' => $maim['roll']]], $thread, $speaker), null);
            $this->chats->send($chatId, $actorUserId, $maim['text'], $this->withThread([[
                'type' => 'injury-details',
                'payload' => $maim['details'],
            ],
            ], $thread, $speaker), null);
        }
    }

    /**
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     *
     * @return string|null Код раны или null.
     */
    private function cutting(array $hooks): ?string
    {
        foreach ($hooks as $hook) {
            if ($hook['handler'] instanceof IDamageAsWounds) {
                return $this->stateCode($hook['payload']);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload Payload.
     */
    private function stateCode(array $payload): string
    {
        $code = $payload['stateRuleCode'] ?? $payload['state_code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game strike hook is invalid');
        }

        return $code;
    }

    /**
     * @return list<array{handler: IMechanicHandler, payload: array<string, mixed>}>
     */
    private function hooks(CharacterRuleSlice $slice, string $damageTypeCode): array
    {
        $rule = $slice->findLive($damageTypeCode);
        if (!$rule instanceof CharacterResolvedRule) {
            throw new GameInvalidException('Game strike damage type is invalid');
        }

        $hooks = [];
        foreach ($rule->getMechanics() as $row) {
            if (!is_array($row) || !is_int($row['mechanic_id'] ?? null) || !is_array($row['mechanic_payload'] ?? null)) {
                throw new GameInvalidException('Game strike hook is invalid');
            }

            $hooks[] = [
                'handler' => $this->handler($rule->getCode(), $row['mechanic_id']),
                'payload' => $row['mechanic_payload'],
            ];
        }

        return $hooks;
    }

    private function handler(string $ruleCode, int $mechanicId): IMechanicHandler
    {
        try {
            $resolved = $this->engine->resolveActive(
                [new MechanicBinding($ruleCode, $mechanicId, null)],
                $this->mechanics->getList(),
                new ResolveActiveOptions(),
            );
        } catch (MechanicInvalidException $exception) {
            throw new GameInvalidException('Game strike hook is invalid', $exception);
        }

        return $resolved[0]->getHandler();
    }

    /**
     * @param array<string, mixed> $payload Payload.
     */
    private function exhaustionAmount(IMechanicHandler $handler, array $payload, int $quotient): ?int
    {
        if ($quotient === 0) {
            return null;
        }

        if ($handler instanceof IExhaustionAmount) {
            $this->stateCode($payload);

            return $quotient;
        }

        if ($handler instanceof IExhaustionWound) {
            $multiplier = $payload['multiplier'] ?? 1;
            if (!is_int($multiplier)) {
                throw new GameInvalidException('Game strike hook is invalid');
            }

            $this->stateCode($payload);

            return $quotient * $multiplier;
        }

        return null;
    }

    /**
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param array<string, mixed> $sheet Лист.
     */
    private function blunt(
        array $hooks,
        int $success,
        DimensionalNumber $injury,
        CharacterRuleSlice $slice,
        array $sheet,
        ?DimensionalNumber $endurance = null,
    ): bool {
        foreach ($hooks as $hook) {
            if (!$hook['handler'] instanceof IBluntUnconscious) {
                continue;
            }

            $threshold = $hook['payload']['successAtLeast'] ?? 6;
            if (!is_int($threshold)) {
                $threshold = 6;
            }

            $endurance ??= $this->endurance($slice, $sheet);

            return $success >= $threshold || $injury->compare($endurance) >= 0;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function woundStrength(CharacterRuleSlice $slice, array $sheet, DimensionalNumber $injury): int
    {
        $endurance = $this->endurance($slice, $sheet);
        $unit = new DimensionalNumber($endurance->getBase(), $endurance->getSize() - 2);
        try {
            return $injury->divide($unit)->getQuotient();
        } catch (RuleInvalidException $exception) {
            throw new GameInvalidException('Game strike wound is invalid', $exception);
        }
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function endurance(CharacterRuleSlice $slice, array $sheet): DimensionalNumber
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && $spec->isDamageEndurance()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        try {
            $value = $this->contexts->build($sheet)->findCharacteristic($matched[0]);
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike endurance is invalid', $exception);
        }

        if (!$value instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{roll: array<string, mixed>, operation: array<string, mixed>|null}
     */
    private function will(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $quotient): array
    {
        $this->requireWillpower($slice);
        $exhaustion = $this->singleFlag($slice, 'exhaustion');
        $spec = $this->stateSpec($slice, $exhaustion);
        $checkCode = $spec->getCheckCode();
        if ($checkCode === null || $checkCode === '') {
            throw new GameInvalidException('Game strike will check is invalid');
        }

        $check = $this->checkSpec($slice, $checkCode);
        $difficulty = $check->getDifficulty();
        if ($difficulty->getKind() !== 'from_state' || $difficulty->getStateCode() !== $exhaustion) {
            throw new GameInvalidException('Game strike will check is invalid');
        }

        $after = $this->storedExhaustion($sheet, $exhaustion) + $quotient;
        $roll = $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            ['base' => $after, 'size' => 0],
        );
        $roll['label'] = $slice->findLive($checkCode)?->getName() ?? 'Проверка истощения';
        $next = $this->outcomeCode($slice, $checkCode, $roll['rating']);
        $current = $this->currentDecline($slice, $sheet);
        $operation = $this->declineOperation($quotient > 0, $current, $next);

        return ['roll' => $roll, 'operation' => $operation, 'outcome' => $this->outcomeKind($next)];
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     *
     * @return array<string, mixed> Бросок.
     */
    private function maim(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $difficulty, array $hooks): array
    {
        $code = $this->singleFlag($slice, 'maim');
        $spec = $this->stateSpec($slice, $code);
        $checkCode = $spec->getCheckCode();
        if ($checkCode === null || $checkCode === '') {
            $checkCode = $this->injuryCheckCode($slice);
        }
        if ($spec->getValueType() !== 'number' || $checkCode === null) {
            throw new GameInvalidException('Game strike maim check is invalid');
        }

        $roll = $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            ['base' => $difficulty, 'size' => 0],
            new GameCheckThrowOverride(null, 4, $this->injuryAdvantage($hooks)),
        );
        $roll['label'] = $slice->findLive($checkCode)?->getName() ?? 'Проверка на увечье';

        return $roll;
    }

    /**
     * Сумма помех injury_efficiency. Нет delta в payload — −1.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     *
     * @return int Сумма delta.
     */
    /**
     * Срок увечья по силе. Та же карта, что у проверки увечья на фронте.
     *
     * @return array{permanent: bool, healTotal: int, healUnit: string}
     */
    private function maimSidecar(int $strength): array
    {
        $spec = match (true) {
            $strength <= 1 => [1, 6, 'days'],
            $strength === 2 => [2, 6, 'days'],
            $strength === 3 => [4, 6, 'days'],
            $strength === 4 => [1, 6, 'decades'],
            $strength === 5 => [1, 5, 'months'],
            default => [1, 6, 'years'],
        };
        $total = 0;
        for ($i = 0; $i < $spec[0]; $i++) {
            $total += random_int(1, $spec[1]);
        }

        return ['permanent' => false, 'healTotal' => $total, 'healUnit' => $spec[2]];
    }

    private function injuryAdvantage(array $hooks): int
    {
        $delta = 0;
        foreach ($hooks as $hook) {
            if (!$hook['handler'] instanceof IInjuryEfficiency) {
                continue;
            }

            $value = $hook['payload']['delta'] ?? -1;
            if (!is_int($value)) {
                throw new GameInvalidException('Game strike hook is invalid');
            }

            $delta += $value;
        }

        return $delta;
    }

    private function injuryCheckCode(CharacterRuleSlice $slice): ?string
    {
        $rule = $slice->findLive('check-injury');
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CheckSpec || $rule === null) {
            return null;
        }

        return $rule->getCode();
    }

    private function requireWillpower(CharacterRuleSlice $slice): void
    {
        $matched = 0;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && $spec->isWillpower()) {
                $matched++;
            }
        }

        if ($matched !== 1) {
            throw new GameInvalidException('Game strike willpower is invalid');
        }
    }

    /**
     * @return array<string, mixed>|null Операция или null, если шкала не двигается.
     */
    private function declineOperation(bool $rose, ?int $current, ?array $next): ?array
    {
        $nextRank = $next['rank'] ?? 0;
        $currentRank = $current ?? 0;
        if ($rose && $nextRank <= $currentRank) {
            return null;
        }

        if (!$rose && $nextRank >= $currentRank) {
            return null;
        }

        if ($next === null) {
            return ['kind' => 'replaceDecline'];
        }

        return ['kind' => 'replaceDecline', 'stateRuleCode' => $next['code']];
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed>|null $operation replaceDecline или null.
     *
     * @return list<array<string, mixed>> removeState и putState.
     */
    private function declineSheetOps(array $sheet, CharacterRuleSlice $slice, ?array $operation): array
    {
        if ($operation === null) {
            return [];
        }

        $ops = [];
        $states = $sheet['states'] ?? [];
        if (is_array($states)) {
            foreach ($states as $row) {
                if (!is_array($row) || !is_int($row['id'] ?? null)) {
                    continue;
                }
                $code = $row['stateRuleCode'] ?? null;
                $spec = is_string($code) ? $this->optionalState($slice, $code) : null;
                if ($spec !== null && $this->rank($spec) !== null) {
                    $ops[] = ['kind' => 'removeState', 'id' => $row['id']];
                }
            }
        }

        $next = $operation['stateRuleCode'] ?? null;
        if (is_string($next) && $next !== '') {
            $ops[] = ['kind' => 'putState', 'stateRuleCode' => $next];
        }

        return $ops;
    }

    /**
     * @return array{code: string, rank: int}|null
     */
    private function outcomeCode(CharacterRuleSlice $slice, string $checkCode, int $rating): ?array
    {
        if ($rating >= 0) {
            return null;
        }

        $flag = $rating <= -3 ? 'unconscious' : ($rating === -2 ? 'disabled' : 'weakness');
        $matched = null;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof StateSpec) {
                continue;
            }
            $selected = match ($flag) {
                'unconscious' => $spec->isDeclineUnconscious(),
                'disabled' => $spec->isDeclineDisabled(),
                default => $spec->isDeclineWeakness(),
            };
            if (!$selected) {
                continue;
            }
            if ($matched !== null) {
                throw new GameInvalidException('Game strike will outcome is invalid');
            }
            $matched = $rule->getCode();
        }

        if (!is_string($matched)) {
            throw new GameInvalidException('Game strike will outcome is invalid');
        }

        $spec = $this->stateSpec($slice, $matched);
        $rank = $this->rank($spec);
        if ($spec->getValueType() !== 'flag' || $rank === null) {
            throw new GameInvalidException('Game strike will outcome is invalid');
        }

        return ['code' => $matched, 'rank' => $rank];
    }

    /**
     * @param array{code: string, rank: int}|null $next Исход шкалы.
     */
    private function outcomeKind(?array $next): string
    {
        return match ($next['rank'] ?? null) {
            1 => 'weakness',
            2 => 'disabled',
            3 => 'unconscious',
            default => 'clear',
        };
    }

    private function rank(StateSpec $spec): ?int
    {
        $flags = [$spec->isDeclineWeakness(), $spec->isDeclineDisabled(), $spec->isDeclineUnconscious()];
        if (count(array_filter($flags)) !== 1) {
            return null;
        }

        if ($spec->isDeclineWeakness()) {
            return 1;
        }

        return $spec->isDeclineDisabled() ? 2 : 3;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function currentDecline(CharacterRuleSlice $slice, array $sheet): ?int
    {
        $rank = null;
        foreach ($this->stateRows($sheet) as $code) {
            $spec = $this->optionalState($slice, $code);
            $rowRank = $spec === null ? null : $this->rank($spec);
            if ($rowRank === null) {
                continue;
            }

            $rank = max($rank ?? 0, $rowRank);
        }

        return $rank;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function hasFlag(CharacterRuleSlice $slice, array $sheet, string $flag): bool
    {
        foreach ($this->stateRows($sheet) as $code) {
            $spec = $this->optionalState($slice, $code);
            if ($spec !== null && $this->flag($spec, $flag)) {
                return true;
            }
        }

        return false;
    }

    private function flag(StateSpec $spec, string $flag): bool
    {
        if ($flag === 'unconscious') {
            return $spec->isDeclineUnconscious();
        }

        if ($flag === 'exhaustion') {
            return $spec->isDamageExhaustion();
        }

        return $spec->isMaim();
    }

    private function singleFlag(CharacterRuleSlice $slice, string $flag): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $this->flag($spec, $flag)) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike state is invalid');
        }

        return $matched[0];
    }

    private function stateSpec(CharacterRuleSlice $slice, string $code): StateSpec
    {
        $spec = $this->optionalState($slice, $code);
        if (!$spec instanceof StateSpec) {
            throw new GameInvalidException('Game strike state is invalid');
        }

        return $spec;
    }

    private function optionalState(CharacterRuleSlice $slice, string $code): ?StateSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof StateSpec ? $spec : null;
    }

    private function checkSpec(CharacterRuleSlice $slice, string $code): CheckSpec
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof CheckSpec) {
            throw new GameInvalidException('Game strike will check is invalid');
        }

        return $spec;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function storedExhaustion(array $sheet, string $code): int
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        $value = 0;
        foreach ($states as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $code) {
                continue;
            }

            if (!is_int($row['value'] ?? null)) {
                throw new GameInvalidException('Game strike exhaustion is invalid');
            }

            $value = $row['value'];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<int, string> Коды строк.
     */
    private function stateRows(array $sheet): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        $codes = [];
        foreach ($states as $row) {
            $code = is_array($row) ? ($row['stateRuleCode'] ?? null) : null;
            if (is_string($code) && $code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param list<array<string, mixed>> $attachments Вложения.
     * @param array{id: string, kind: string, parentId?: string} $thread Тред атаки.
     * @param array<string, mixed>|null $speaker Говорящий персонаж или NPC.
     *
     * @return list<array<string, mixed>> Вложения с носителем треда.
     */
    private function withThread(array $attachments, array $thread, ?array $speaker): array
    {
        $attachments[] = ['type' => 'chat_system', 'payload' => ['thread' => $thread]];
        if ($speaker !== null) {
            $attachments[] = ['type' => 'chat_speaker', 'payload' => $speaker];
        }

        return $attachments;
    }
}
