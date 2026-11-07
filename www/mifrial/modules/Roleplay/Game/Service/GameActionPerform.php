<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Общий серверный сценарий не-атакующего действия: стоимость, лист, ожидание.
 */
final class GameActionPerform
{
    /**
     * Создаёт сценарий.
     *
     * @param GameStrikeRules $rules Правила игры.
     * @param GameActionCards $cards Карточки действий.
     * @param GameStrikeSheetWrites $sheetWrites Запись листов.
     * @param GameActionPerformSteps $steps Шаги сценария.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameActionCards $cards,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameActionPerformSteps $steps,
    ) {
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
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
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
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
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
     * Считает цену действия без записи листа.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param bool $allowZeroChosen Разрешить chosen 0.
     * @param array<string, int> $chosenAmounts Выбранные суммы.
     * @param ProcessStep|null $step Шаг процесса.
     *
     * @return array{
     *     slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice,
     *     document: array<string, mixed>,
     *     priced: array<string, mixed>,
     *     spends: array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend>
     * } Цена.
     *
     * @throws GameInvalidException Если карточка не подходит.
     * @throws GameNotFoundException Если листа нет.
     */
    public function quote(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        array $pending,
        bool $allowZeroChosen = false,
        array $chosenAmounts = [],
        ?ProcessStep $step = null,
    ): array {
        return $this->steps->quote(
            $game,
            $actorKind,
            $actorId,
            $actionRuleCode,
            $pending,
            $allowZeroChosen,
            $chosenAmounts,
            $step,
        );
    }

    /**
     * Цена описательного шага процесса: лист актора и траты шага без карточки.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param ProcessStep $step Шаг процесса.
     *
     * @return array{
     *     document: array{
     *         actualVersion: int,
     *         rulesRevision: int,
     *         choices: array<string, mixed>,
     *         sheet: array<string, mixed>
     *     },
     *     spends: array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend>
     * } Цена шага.
     *
     * @throws GameNotFoundException Если листа нет.
     */
    public function quoteStep(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        ProcessStep $step,
    ): array {
        return $this->steps->quoteStep($game, $actorKind, $actorId, $step);
    }

    /**
     * Проверяет достаточность ресурсов для готовых трат.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     *
     * @return void
     *
     * @throws GameInvalidException Если ресурсов не хватает.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function assertSpends(GameRecord $game, array $sheet, array $spends): void
    {
        $this->steps->assertResources($game, $sheet, $spends);
    }

    /**
     * Списывает только ресурсы без операций листа.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     *
     * @return int Новая версия.
     *
     * @throws GameBattleConflictException Если версия другой.
     * @throws GameInvalidException Если трата отвергнута.
     * @throws GameNotFoundException Если листа нет.
     */
    public function spend(
        GameRecord $game,
        string $actorKind,
        int $actorId,
        int $expectedSheetVersion,
        array $spends,
    ): int {
        if ($spends === []) {
            return $expectedSheetVersion;
        }

        return $this->sheetWrites->write(
            $game,
            $actorKind,
            $actorId,
            $expectedSheetVersion,
            [],
            null,
            null,
            $spends,
        );
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
     * @param ProcessStep|null $step Шаг процесса.
     * @param bool $paid Действие уже оплачено.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     sheetVersion: int,
     *     spent: int,
     *     amounts: array<string, int>,
     *     rolls: list<array{roll: array<string, mixed>, text: string}>
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
        ?ProcessStep $step = null,
        bool $paid = false,
    ): array {
        return $this->steps->perform(new GameActionPerformCall(
            $game,
            $actorKind,
            $actorId,
            $actionRuleCode,
            $expectedSheetVersion,
            $pending,
            $allowZeroChosen,
            $chosenAmounts,
            $projectConflict,
            $step,
            $paid,
        ));
    }
}
