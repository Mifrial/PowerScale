<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Общий серверный сценарий не-атакующего действия: стоимость, лист, ожидание.
 */
final class GameActionPerform
{
    private readonly GameActionChosenAmounts $chosen;

    /**
     * Создаёт сценарий.
     *
     * @param GameStrikeRules $rules Правила игры.
     * @param GameNpcRepository $npcs NPC игры.
     * @param GameStrikeSheetWrites $sheetWrites Запись листов.
     * @param GameActionCards $cards Карточки действий.
     * @param GamePendingActionCosts $pendingCosts Карта ожидания.
     * @param GameCardAvailability $availability Принадлежность и требования карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameActionCards $cards,
        private readonly GamePendingActionCosts $pendingCosts,
        private readonly GameCardAvailability $availability,
    ) {
        $this->chosen = new GameActionChosenAmounts();
    }

    /**
     * Нужен ли карточке предыдущий удар.
     *
     * @param GameRecord $game Игра.
     * @param string $actionRuleCode Карточка.
     *
     * @return bool true, если карточка требует снимок прошлой атаки.
     *
     * @throws GameInvalidException Если карточка не-атакующая, но серверу не по силам.
     */
    public function requiresFollowUp(GameRecord $game, string $actionRuleCode): bool
    {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->cards->assertServerPerformable($slice, $actionRuleCode);

        return $this->cards->hasRequirePreviousAttack($spec);
    }

    /**
     * Реакция цели прошлого удара для карточки с require_previous_attack.
     *
     * @param GameRecord $game Игра.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $targetKey Ключ цели.
     *
     * @return string Реакция цели.
     *
     * @throws GameInvalidException Если карточка, снимок или цель не подходят.
     */
    public function followUpReaction(
        GameRecord $game,
        string $actionRuleCode,
        array $pending,
        string $actorKind,
        int $actorId,
        string $targetKey,
    ): string {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->cards->assertServerPerformable($slice, $actionRuleCode);
        if (!$this->cards->hasRequirePreviousAttack($spec)) {
            throw new GameInvalidException('Game action card requires no previous attack');
        }

        $lastStrike = new GameLastStrikeSnapshot();
        $lastStrike->assertFollowUp(
            $slice,
            $actionRuleCode,
            $pending,
            $actorKind,
            $actorId,
            [$targetKey],
        );
        $reaction = $lastStrike->reactionOf($pending, $actorKind, $actorId, $targetKey);
        if ($reaction === null) {
            throw new GameInvalidException('Game action reaction is missing');
        }

        return $reaction;
    }

    /**
     * Списывает стоимость, пишет лист и закрывает ожидание действия.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка.
     * @param int $expectedSheetVersion Версия листа актора.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param bool $allowZeroChosen Разрешить chosen 0 (Ожидание).
     * @param array<string, int> $chosenAmounts Выбранные суммы ресурсов.
     * @param Closure|null $projectConflict Проектор конфликта листа.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     sheetVersion: int,
     *     spent: int,
     *     amounts: array<string, int>
     * } Итог.
     *
     * @throws GameInvalidException Если карточка или ресурсы не подходят.
     * @throws GameNotFoundException Если листа актора нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    public function perform(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        int $expectedSheetVersion,
        array $pending,
        bool $allowZeroChosen = false,
        array $chosenAmounts = [],
        ?Closure $projectConflict = null,
    ): array {
        $executed = $this->execute(
            $game,
            $actorKind,
            $actorId,
            $actionRuleCode,
            $expectedSheetVersion,
            $pending,
            $allowZeroChosen,
            $chosenAmounts,
            $projectConflict,
        );

        return $this->finish($executed, $actionRuleCode, $actorKind, $actorId);
    }

    /**
     * Готовит, проверяет и пишет лист действия.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $pending Ожидание.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     * @param array<string, int> $chosenAmounts Выбранные суммы ресурсов.
     * @param Closure|null $projectConflict Проектор конфликта.
     *
     * @return array{prepared: array<string, mixed>, priced: array<string, mixed>, sheetVersion: int} Итог.
     *
     * @throws GameInvalidException Если карточка или ресурсы не подходят.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    private function execute(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        int $expectedSheetVersion,
        array $pending,
        bool $allowZeroChosen,
        array $chosenAmounts,
        ?Closure $projectConflict,
    ): array {
        $prepared = $this->prepare(
            $game,
            $actorKind,
            $actorId,
            $actionRuleCode,
            $allowZeroChosen,
            $chosenAmounts,
        );
        $priced = $this->priceFor($prepared, $actionRuleCode, $actorKind, $actorId, $pending);
        $this->assertResources($game, $prepared['document']['sheet'], $priced['spends']);
        $sheetVersion = $this->writePrepared(
            $game,
            $actorKind,
            $actorId,
            $expectedSheetVersion,
            $priced,
            $projectConflict,
        );

        return ['prepared' => $prepared, 'priced' => $priced, 'sheetVersion' => $sheetVersion];
    }

    /**
     * Применяет cost-эффекты к подготовленным тратам.
     *
     * @param array<string, mixed> $prepared Подготовка.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     spends: array<int, ResourceSpend>,
     *     amounts: array<string, int>
     * } Цена.
     */
    private function priceFor(
        array $prepared,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
    ): array {
        return $this->price(
            $prepared['spends'],
            $prepared['slice'],
            $prepared['document']['choices'],
            $actionRuleCode,
            $actorKind,
            $actorId,
            $pending,
        );
    }

    /**
     * Готовит срез, лист и разрешённые траты.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     * @param array<string, int> $chosenAmounts Выбранные суммы ресурсов.
     *
     * @return array{
     *     slice: CharacterRuleSlice,
     *     document: array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>},
     *     spends: array<int, ResourceSpend>
     * } Подготовка.
     *
     * @throws GameInvalidException Если карточка или ресурсы не подходят.
     * @throws GameNotFoundException Если листа нет.
     */
    private function prepare(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        bool $allowZeroChosen,
        array $chosenAmounts,
    ): array {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->cards->assertServerPerformable($slice, $actionRuleCode);
        $this->availability->assertAvailable($game, $actorKind, $actorId, $actionRuleCode);
        $document = $this->document($game, $actorKind, $actorId);
        $chosen = $this->chosen->resolve($slice, $spec, $document['sheet'], $allowZeroChosen);
        $chosen = array_replace($chosen, $chosenAmounts);
        $spends = $this->resolveSpends($game, $actionRuleCode, $document, $chosen, $allowZeroChosen);

        return ['slice' => $slice, 'document' => $document, 'spends' => $spends];
    }

    /**
     * Закрывает ожидание и собирает итог.
     *
     * @param array{prepared: array<string, mixed>, priced: array<string, mixed>, sheetVersion: int} $executed Итог.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     sheetVersion: int,
     *     spent: int,
     *     amounts: array<string, int>
     * } Итог.
     */
    private function finish(
        array $executed,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
    ): array {
        $priced = $executed['priced'];
        $closed = $this->pendingCosts->complete(
            $executed['prepared']['slice'],
            $executed['prepared']['document']['choices'],
            $actionRuleCode,
            $actorKind,
            $actorId,
            $priced['pending'],
            $priced['amounts'],
            [],
        );

        return [
            'pending' => $closed,
            'sheetVersion' => $executed['sheetVersion'],
            'spent' => $priced['amounts']['action-points'] ?? 0,
            'amounts' => $priced['amounts'],
        ];
    }

    /**
     * Разрешает стоимость карточки с учётом chosen.
     *
     * @param GameRecord $game Игра.
     * @param string $actionRuleCode Карточка.
     * @param array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>} $document Лист.
     * @param array<string, int> $chosen Значения chosen.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     *
     * @return array<int, ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если ресурсы не подходят.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function resolveSpends(
        GameRecord $game,
        string $actionRuleCode,
        array $document,
        array $chosen,
        bool $allowZeroChosen,
    ): array {
        return $this->rules->resolveResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $actionRuleCode,
            0,
            '',
            $document['choices'],
            $document['sheet'],
            $chosen,
            $allowZeroChosen,
        );
    }

    /**
     * Проверяет достаточность ресурсов.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return void
     *
     * @throws GameInvalidException Если ресурсов не хватает.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertResources(GameRecord $game, array $sheet, array $spends): void
    {
        if (
            $this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $sheet,
                $spends,
            )
        ) {
            return;
        }

        throw new GameInvalidException('Game action resources are insufficient');
    }

    /**
     * Пишет лист актора по цене действия.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind character или npc.
     * @param int $actorId Идентификатор актора.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $priced Цена.
     * @param Closure|null $projectConflict Проектор конфликта.
     *
     * @return int Новая версия.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если операция отвергнута.
     * @throws GameNotFoundException Если листа нет.
     */
    private function writePrepared(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        int $expectedSheetVersion,
        array $priced,
        ?Closure $projectConflict,
    ): int {
        return $this->sheetWrites->write(
            $game,
            $actorKind,
            $actorId,
            $expectedSheetVersion,
            [],
            ['type' => $actorKind, 'id' => $actorId],
            $projectConflict,
            $priced['spends'],
        );
    }

    /**
     * Применяет cost-эффекты и собирает итоговые траты.
     *
     * @param array<int, ResourceSpend> $spends Разрешённые траты.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param array<string, mixed> $pending Карта ожидания.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     spends: array<int, ResourceSpend>,
     *     amounts: array<string, int>
     * } Итог.
     */
    private function price(
        array $spends,
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
    ): array {
        $split = $this->splitSpends($spends);
        $declared = $this->pendingCosts->declare(
            $slice,
            $choices,
            $actionRuleCode,
            $actorKind,
            $actorId,
            $pending,
            $split['amounts'],
            false,
        );

        return [
            'pending' => $declared['pending'],
            'spends' => [...$split['kept'], ...$this->spendsOf($declared['amounts'])],
            'amounts' => $declared['amounts'],
        ];
    }

    /**
     * Делит разрешённые траты на целые суммы и неразобранные записи.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array{amounts: array<string, int>, kept: array<int, ResourceSpend>} Итог.
     */
    private function splitSpends(array $spends): array
    {
        $amounts = [];
        $kept = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                $kept[] = $spend;
                continue;
            }

            $code = $spend->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $native;
        }

        return ['amounts' => $amounts, 'kept' => $kept];
    }

    /**
     * Собирает траты из итоговых целых сумм.
     *
     * @param array<string, int> $amounts Суммы.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function spendsOf(array $amounts): array
    {
        $spends = [];
        foreach ($amounts as $code => $amount) {
            $spends[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return $spends;
    }

    /**
     * Авторитетный лист актора.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     *
     * @return array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     *
     * @throws GameInvalidException Если лист NPC битый.
     * @throws GameNotFoundException Если актора нет или он чужой.
     */
    private function document(GameRecord $game, string $actorKind, int $actorId): array
    {
        if ($actorKind === 'character') {
            $snapshot = $this->rules->characterSnapshot($actorId);

            return [
                'actualVersion' => $snapshot['actualVersion'],
                'choices' => $snapshot['choices'],
                'sheet' => $snapshot['sheet'],
            ];
        }

        $npc = $this->npcs->getById($actorId);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game action npc was not found');
        }

        $version = $npc->getVersion();
        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game action npc sheet is invalid');
        }

        return [
            'actualVersion' => $npc->getActualVersion(),
            'choices' => $choices,
            'sheet' => $sheet,
        ];
    }
}
