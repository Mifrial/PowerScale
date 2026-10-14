<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh

use Mifrial\Core\Event\Interface\Service\IEventManager;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;
use Mifrial\Roleplay\Game\Repository\GameWoundCommandRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ActionResourceCutGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\WoundBandageAidGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\SpeciesSpec;

/**
 * Зажать, отпустить и перевязать: лист, ресурс, карта ожидания и одно сообщение чата.
 */
final class GameWoundActions
{
    private readonly GameBattleRepository $battles;

    private readonly GameWoundCommandRepository $commands;

    private readonly GameNpcRepository $npcs;

    private readonly GameCharacterRepository $characters;

    private readonly GameMemberRepository $members;

    private readonly GameDeliverySignal $delivery;

    private readonly GameSessionRepository $sessions;

    private readonly GameStrikeSheetWrites $sheetWrites;

    private readonly GameReplayTransaction $replayTransaction;

    private readonly GamePendingActionCosts $pendingCosts;

    /**
     * Создаёт сценарий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     * @param IGames $games Строка игры.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     * @param GameStrikeRules $rules Срез.
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param IChats $chats Чат игры.
     * @param IEventManager $events Доставка.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
        private readonly IGames $games,
        private readonly GameCardAccess $cardAccess,
        private readonly GameStrikeRules $rules,
        ICharacterActualMutations $mutations,
        private readonly IChats $chats,
        IEventManager $events,
    ) {
        $this->battles = new GameBattleRepository($smartTableGateway);
        $this->commands = new GameWoundCommandRepository($smartTableGateway);
        $this->npcs = new GameNpcRepository($smartTableGateway);
        $this->characters = new GameCharacterRepository($smartTableGateway);
        $this->members = new GameMemberRepository($smartTableGateway);
        $this->sessions = new GameSessionRepository($smartTableGateway);
        $this->sheetWrites = new GameStrikeSheetWrites($mutations, $this->npcs);
        $this->replayTransaction = new GameReplayTransaction($smartTableGateway);
        $this->pendingCosts = new GamePendingActionCosts();
        $this->delivery = new GameDeliverySignal($events);
    }

    /**
     * Выполняет зажим, отпускание или перевязку.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Тело команды.
     *
     * @return array<string, mixed> Узкий итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если игры или цели нет.
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function act(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $game = $this->games->get($gameId);
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException('Game was not found');
        }

        $this->assertWriter($game, $actorUserId, $editAll, $body);
        $sessionId = $this->sessions->findSessionId($gameId);
        $key = $body['idempotencyKey'] ?? null;
        if ($sessionId === null || !is_string($key) || $key === '') {
            throw new GameInvalidException('Game wound action is invalid');
        }

        return $this->commit($game, $actorUserId, $sessionId, $key, $body);
    }

    /**
     * Пишет журнал и сигнал.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param int $sessionId Сессия.
     * @param string $key Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     */
    private function commit(
        GameRecord $game,
        int $actorUserId,
        int $sessionId,
        string $key,
        array $body,
    ): array {
        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), $key),
            fn (): int => $this->commands->reserve($game->getId(), $sessionId, $key, $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->perform($game, $actorUserId, $body),
            $body,
        );
        $this->announce($game->getId(), $key, $execution['replay']);

        return $execution['result'];
    }

    /**
     * Шлёт сигнал после первой записи.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param bool $replay Повтор.
     *
     * @return void
     */
    private function announce(int $gameId, string $idempotencyKey, bool $replay): void
    {
        if ($replay) {
            return;
        }

        $stored = $this->commands->findByKey($gameId, $idempotencyKey);
        if ($stored !== null) {
            $this->delivery->recorded($gameId, 'wound', $stored['id'], []);
        }
    }

    /**
     * Пишет лист, карту и чат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если команда отвергнута.
     * @throws GameNotFoundException Если цели нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function perform(GameRecord $game, int $actorUserId, array $body): array
    {
        $operation = $body['operation'] ?? null;
        $actor = $this->pair($body['actor'] ?? null);
        $target = $this->pair($body['target'] ?? null);
        $stateId = $body['stateId'] ?? null;
        $actionRuleCode = $body['actionRuleCode'] ?? null;
        $expected = $body['expectedSheetVersion'] ?? null;
        if (!is_string($operation) || !is_int($stateId) || !is_string($actionRuleCode) || !is_int($expected)) {
            throw new GameInvalidException('Game wound action is invalid');
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $actorSheet = $this->document($game, $actor['kind'], $actor['id']);
        $targetSheet = $this->document($game, $target['kind'], $target['id']);
        $row = $this->rowOf($targetSheet['sheet'], $stateId);
        $patch = $this->patch($operation, $slice, $actorSheet, $row, $actor, $game);
        $priced = $this->priced($slice, $actorSheet['choices'], $actionRuleCode, $actor, $body);
        $version = $this->sheetWrites->write(
            $game,
            $target['kind'],
            $target['id'],
            $expected,
            [$patch],
            null,
            null,
            $priced['spends'],
        );
        $battleVersion = $this->storePending($body, $priced['pending']);
        $this->say($game, $actorUserId, $operation);
        $result = [
            'operation' => $operation,
            'target' => $target,
            'stateId' => $stateId,
            'sheetVersion' => $version,
            'battleVersion' => $battleVersion,
        ];

        return $result;
    }

    /**
     * Правка строки по операции.
     *
     * @param string $operation hold, release или bandage.
     * @param CharacterRuleSlice $slice Срез.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $actorSheet Лист актора.
     * @param array<string, mixed> $row Строка раны.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return array<string, mixed> patchState.
     *
     * @throws GameInvalidException Если операция отвергнута.
     */
    private function patch(
        string $operation,
        CharacterRuleSlice $slice,
        array $actorSheet,
        array $row,
        array $actor,
        GameRecord $game,
    ): array {
        if (($row['internal'] ?? false) === true && $operation !== 'release') {
            throw new GameInvalidException('Game wound is internal');
        }

        if ($operation === 'hold') {
            return $this->holdPatch($slice, $actorSheet, $row, $actor, $game);
        }

        if ($operation === 'release') {
            return ['kind' => 'patchState', 'id' => $row['id'], 'heldBy' => null];
        }

        if ($operation === 'bandage') {
            return $this->bandagePatch($slice, $actorSheet['choices'], $row);
        }

        throw new GameInvalidException('Game wound action is invalid');
    }

    /**
     * Зажим, если рука свободна и строка ещё не зажата.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $actorSheet Лист актора.
     * @param array<string, mixed> $row Строка.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если руки нет или зажим уже стоит.
     */
    private function holdPatch(
        CharacterRuleSlice $slice,
        array $actorSheet,
        array $row,
        array $actor,
        GameRecord $game,
    ): array {
        if (($row['heldBy'] ?? null) !== null) {
            throw new GameInvalidException('Game wound is already held');
        }

        if ($this->freeHands($slice, $actorSheet, $actor, $game) < 1) {
            throw new GameInvalidException('Game wound hold has no free hand');
        }

        return ['kind' => 'patchState', 'id' => $row['id'], 'heldBy' => $actor];
    }

    /**
     * Перевязка: плюс 2, не выше силы и потолка 4, пока нет гранта помощи.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если сила не число.
     */
    private function bandagePatch(CharacterRuleSlice $slice, array $choices, array $row): array
    {
        $strength = $row['value'] ?? null;
        $bandage = $row['bandage'] ?? 0;
        if (!is_int($strength) || !is_int($bandage)) {
            throw new GameInvalidException('Game wound strength is invalid');
        }

        $aided = $this->hasAid($slice, $choices) || ($row['aided'] ?? false) === true;
        $next = $bandage + 2;
        if (!$aided) {
            $next = min($next, 4);
        }

        return [
            'kind' => 'patchState',
            'id' => $row['id'],
            'bandage' => min($next, $strength),
            'aided' => $aided,
        ];
    }

    /**
     * Цены карточки, срез, ожидание и пол 2 у затронутого ресурса.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка.
     * @param array{kind: string, id: int} $actor Актор.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>} Трата и карта.
     *
     * @throws GameInvalidException Если у карточки нет ресурса.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    private function priced(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        array $actor,
        array $body,
    ): array {
        $amounts = $this->cardAmounts($slice, $actionRuleCode);
        $touched = $this->applyCuts($slice, $choices, $actionRuleCode, $amounts);
        $battle = $this->battleOf($body);
        $pending = $battle?->getPendingActionEffects() ?? [];
        if ($battle !== null) {
            $turned = $this->pendingCosts->turn(
                $slice,
                $choices,
                $actionRuleCode,
                $actor['kind'],
                $actor['id'],
                $pending,
                $amounts,
            );
            $amounts = $turned['amounts'];
            $pending = $turned['pending'];
            $touched = array_merge($touched, $turned['touched']);
        }

        $spends = [];
        foreach ($amounts as $code => $amount) {
            $value = array_key_exists($code, $touched) ? max($amount, 2) : $amount;
            $spends[] = new ResourceSpend($code, new ScalarResourceValue($value));
        }

        return ['spends' => $spends, 'pending' => $pending];
    }

    /**
     * Пишет карту, если тело назвало бой.
     *
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $pending Карта.
     *
     * @return int|null Новая версия боя или null.
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    private function storePending(array $body, array $pending): ?int
    {
        $battleId = $body['battleId'] ?? 0;
        $expected = $body['expectedBattleVersion'] ?? null;
        if (!is_int($battleId) || $battleId < 1) {
            return null;
        }

        if (!is_int($expected)) {
            throw new GameInvalidException('Game wound battle version is invalid');
        }

        return $this->battles->replacePending($battleId, $expected, $pending)->getStateVersion();
    }

    /**
     * Одно текстовое сообщение в чат игры.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $operation Операция.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет.
     */
    private function say(GameRecord $game, int $actorUserId, string $operation): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $text = match ($operation) {
            'hold' => 'Зажимает рану.',
            'release' => 'Отпускает рану.',
            'bandage' => 'Перевязывает рану.',
            default => throw new GameInvalidException('Game wound action is invalid'),
        };
        $this->chats->send($chatId, $actorUserId, $text, []);
    }

    /**
     * Целые цены всех resource-компонентов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<string, int> Суммы.
     *
     * @throws GameInvalidException Если компонента нет или цена не целая.
     */
    private function cardAmounts(CharacterRuleSlice $slice, string $actionRuleCode): array
    {
        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            throw new GameInvalidException('Game wound action card is invalid');
        }

        $amounts = [];
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent) {
                continue;
            }

            if (!is_int($component->getAmount())) {
                throw new GameInvalidException('Game wound action resource is invalid');
            }

            $code = $component->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $component->getAmount();
        }

        if ($amounts === []) {
            throw new GameInvalidException('Game wound action has no resource');
        }

        return $amounts;
    }

    /**
     * Вычитает безусловные срезы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     * @param string $actionRuleCode Карточка.
     * @param array<string, int> $amounts Цены.
     *
     * @return array<string, bool> Затронутые ресурсы.
     */
    private function applyCuts(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        array &$amounts,
    ): array {
        $touched = [];
        foreach ($this->grants($slice, $choices) as $grant) {
            if (!$grant instanceof ActionResourceCutGrant || $grant->getActionRuleCode() !== $actionRuleCode) {
                continue;
            }

            $code = $grant->getResourceCode();
            if (!array_key_exists($code, $amounts)) {
                continue;
            }

            $amounts[$code] -= $grant->getAmount();
            $touched[$code] = true;
        }

        return $touched;
    }

    /**
     * Есть грант снятия потолка.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return bool true, если грант куплен.
     */
    private function hasAid(CharacterRuleSlice $slice, array $choices): bool
    {
        foreach ($this->grants($slice, $choices) as $grant) {
            if ($grant instanceof WoundBandageAidGrant) {
                return true;
            }
        }

        return false;
    }

    /**
     * Гранты блоков не выше купленного уровня.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<int, object> Гранты.
     */
    private function grants(CharacterRuleSlice $slice, array $choices): array
    {
        $reader = new CharacterDonorGrants();
        $grants = [];
        $abilities = $choices['abilities'] ?? [];
        if (!is_array($abilities)) {
            return [];
        }

        foreach ($abilities as $choice) {
            $code = is_array($choice) ? ($choice['ruleCode'] ?? null) : null;
            $level = is_array($choice) ? ($choice['level'] ?? null) : null;
            $rule = is_string($code) ? $slice->findLive($code) : null;
            if (!$rule instanceof CharacterResolvedRule || !is_int($level)) {
                continue;
            }

            if (!$rule->getSpec() instanceof AbilitySpec) {
                continue;
            }

            foreach ($reader->getForLevel($rule, $code, $level) as $donor) {
                $grants[] = $donor->getGrant();
            }
        }

        return $grants;
    }

    /**
     * Свободные руки держателя.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $actorSheet Лист.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return int Число.
     */
    private function freeHands(CharacterRuleSlice $slice, array $actorSheet, array $actor, GameRecord $game): int
    {
        return $this->handCount($slice, $actorSheet['choices'])
            - $this->equippedHands($slice, $actorSheet['choices'])
            - $this->heldWounds($actor, $game);
    }

    /**
     * Число рук расы. Нет поля — 2.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return int Число.
     */
    private function handCount(CharacterRuleSlice $slice, array $choices): int
    {
        $code = $choices['raceCode'] ?? null;
        $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
        if ($spec instanceof SpeciesSpec) {
            $parent = $spec->getParentRaceCode();
            $spec = is_string($parent) ? $slice->findLive($parent)?->getSpec() : null;
        }

        if (!$spec instanceof RaceSpec || $spec->getHands() === null) {
            return 2;
        }

        return $spec->getHands();
    }

    /**
     * Сумма min занятых рук надетого.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return int Число.
     */
    private function equippedHands(CharacterRuleSlice $slice, array $choices): int
    {
        $occupied = 0;
        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            return 0;
        }

        foreach ($inventory as $row) {
            if (!is_array($row) || ($row['equipped'] ?? false) !== true || !is_string($row['ruleCode'] ?? null)) {
                continue;
            }

            $spec = $slice->findLive($row['ruleCode'])?->getSpec();
            $hands = $spec instanceof ItemSpec ? $spec->getOccupyHands() : null;
            $occupied += $hands === null ? 0 : $hands->getMin();
        }

        return $occupied;
    }

    /**
     * Внешние раны, которые этот держатель уже держит.
     *
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return int Число.
     */
    private function heldWounds(array $actor, GameRecord $game): int
    {
        $held = 0;
        foreach ($this->characters->getListByGame($game->getId(), null) as $membership) {
            $sheet = $this->rules->characterSnapshot($membership->getCharacterId())['sheet'];
            $held += $this->holdsOn($sheet, $actor);
        }

        foreach ($this->npcs->getListByGame($game->getId()) as $npc) {
            $sheet = $npc->getVersion()['sheet'] ?? [];
            $held += is_array($sheet) ? $this->holdsOn($sheet, $actor) : 0;
        }

        return $held;
    }

    /**
     * Зажимы одного листа.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param array{kind: string, id: int} $actor Держатель.
     *
     * @return int Число.
     */
    private function holdsOn(array $sheet, array $actor): int
    {
        $held = 0;
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return 0;
        }

        foreach ($states as $row) {
            $holder = is_array($row) ? ($row['heldBy'] ?? null) : null;
            $external = is_array($row) && ($row['internal'] ?? false) !== true;
            if ($external && $holder === $actor) {
                $held++;
            }
        }

        return $held;
    }

    /**
     * Открытый бой или null.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return GameBattleRecord|null Бой.
     *
     * @throws GameNotFoundException Если названного боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    private function battleOf(array $body): ?GameBattleRecord
    {
        $battleId = $body['battleId'] ?? 0;
        if (!is_int($battleId) || $battleId < 1) {
            return null;
        }

        $battle = $this->battles->find($battleId);
        $expected = $body['expectedBattleVersion'] ?? null;
        if ($battle === null) {
            throw new GameNotFoundException('Game battle was not found');
        }

        if (!is_int($expected) || $battle->getStateVersion() !== $expected) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }

    /**
     * Лист цели или актора.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Id.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    private function document(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            $snapshot = $this->rules->characterSnapshot($id);

            return ['choices' => $snapshot['choices'], 'sheet' => $snapshot['sheet']];
        }

        $npc = $this->npcs->getById($id);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game npc was not found');
        }

        $version = $npc->getVersion();
        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game npc sheet is invalid');
        }

        return ['choices' => $choices, 'sheet' => $sheet];
    }

    /**
     * Строка состояния по id.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param int $stateId Id.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если строки нет.
     */
    private function rowOf(array $sheet, int $stateId): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game wound was not found');
        }

        foreach ($states as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $stateId) {
                return $row;
            }
        }

        throw new GameInvalidException('Game wound was not found');
    }

    /**
     * Пара kind и id.
     *
     * @param mixed $pair Объект.
     *
     * @return array{kind: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function pair(mixed $pair): array
    {
        $kind = is_array($pair) ? ($pair['kind'] ?? null) : null;
        $id = is_array($pair) ? ($pair['id'] ?? null) : null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game wound actor is invalid');
        }

        return ['kind' => $kind, 'id' => $id];
    }

    /**
     * Писатель игры или владелец персонажа-актора.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param array<string, mixed> $body Тело.
     *
     * @return void
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если персонажа нет в игре.
     */
    private function assertWriter(GameRecord $game, int $actorUserId, bool $editAll, array $body): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game->getId(), $actorUserId)) {
            return;
        }

        $actor = $this->pair($body['actor'] ?? null);
        if ($actor['kind'] !== 'character') {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }

        $row = $this->characters->getByPair($game->getId(), $actor['id']);
        if ($row->getCharacterOwnerId() !== $actorUserId) {
            throw new ActionException('AUTH_DENIED', 'Permission denied');
        }
    }

    /**
     * Роль gm.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     *
     * @return bool Да, если gm.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }
}
