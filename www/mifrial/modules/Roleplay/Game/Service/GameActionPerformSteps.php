<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Шаги сценария действия: подготовка, цена, запись и ожидание.
 */
final class GameActionPerformSteps
{
    private readonly GameActionChosenAmounts $chosen;

    private readonly GameActionStates $states;

    /**
     * Создаёт шаги.
     *
     * @param GameActionPerformPorts $ports Порты сценария.
     * @param GameActionPerformDocument $document Лист актора.
     * @param GameActionPerformPrice $price Цена действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPerformPorts $ports,
        private readonly GameActionPerformDocument $document,
        private readonly GameActionPerformPrice $price,
    ) {
        $this->chosen = new GameActionChosenAmounts();
        $this->states = new GameActionStates();
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
     *     slice: CharacterRuleSlice,
     *     document: array<string, mixed>,
     *     priced: array<string, mixed>,
     *     spends: array<int, ResourceSpend>
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
        $prepared = $this->prepare(
            $game,
            $actorKind,
            $actorId,
            $actionRuleCode,
            $allowZeroChosen,
            $chosenAmounts,
            $step,
        );
        $priced = $this->priceFor($prepared, $actionRuleCode, $actorKind, $actorId, $pending, $step);

        return $this->quoteView($prepared, $priced);
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
     *     spends: array<int, ResourceSpend>
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
        return [
            'document' => $this->document->of($game, $actorKind, $actorId),
            'spends' => (new GameProcessRules())->costs($step),
        ];
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
    public function assertResources(GameRecord $game, array $sheet, array $spends): void
    {
        if (
            $this->ports->rules->spends->hasSufficientResources(
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
     * Выполняет действие и закрывает ожидание.
     *
     * @param GameActionPerformCall $call Снимок вызова.
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
    public function perform(GameActionPerformCall $call): array
    {
        return $this->finish($this->execute($call), $call->actionRuleCode, $call->actorKind, $call->actorId);
    }

    /**
     * Ответ на расчёт цены.
     *
     * @param array<string, mixed> $prepared Подготовка.
     * @param array<string, mixed> $priced Цена.
     *
     * @return array{slice: CharacterRuleSlice, document: array<string, mixed>, priced: array<string, mixed>, spends: array<int, ResourceSpend>} Цена.
     */
    private function quoteView(array $prepared, array $priced): array
    {
        return [
            'slice' => $prepared['slice'],
            'document' => $prepared['document'],
            'priced' => $priced,
            'spends' => $priced['spends'],
        ];
    }

    /**
     * Готовит, проверяет и пишет лист действия.
     *
     * @param GameActionPerformCall $call Снимок вызова.
     *
     * @return array{
     *     prepared: array<string, mixed>,
     *     priced: array<string, mixed>,
     *     sheetVersion: int,
     *     rolls: list<array{roll: array<string, mixed>, text: string}>
     * } Итог.
     *
     * @throws GameInvalidException Если карточка или ресурсы не подходят.
     * @throws GameNotFoundException Если листа нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    private function execute(GameActionPerformCall $call): array
    {
        $planned = $this->prepared($call);
        $written = $this->planOperations($call->game, $planned['prepared']);
        $sheetVersion = $this->writeCall($call, $planned, $written['operations']);

        return [
            'prepared' => $planned['prepared'],
            'priced' => $planned['priced'],
            'sheetVersion' => $sheetVersion,
            'rolls' => $written['rolls'],
        ];
    }

    /**
     * Подготавливает лист и цену вызова.
     *
     * @param GameActionPerformCall $call Снимок вызова.
     *
     * @return array{prepared: array<string, mixed>, priced: array<string, mixed>} Подготовка и цена.
     *
     * @throws GameInvalidException Если карточка или ресурсы не подходят.
     * @throws GameNotFoundException Если листа нет.
     */
    private function prepared(GameActionPerformCall $call): array
    {
        $prepared = $this->prepare(
            $call->game,
            $call->actorKind,
            $call->actorId,
            $call->actionRuleCode,
            $call->allowZeroChosen,
            $call->chosenAmounts,
            $call->step,
        );
        $priced = $this->priceFor($prepared, $call->actionRuleCode, $call->actorKind, $call->actorId, $call->pending, $call->step);
        if (!$call->paid) {
            $this->assertResources($call->game, $prepared['document']['sheet'], $priced['spends']);
        }

        return ['prepared' => $prepared, 'priced' => $priced];
    }

    /**
     * Пишет лист вызова.
     *
     * @param GameActionPerformCall $call Снимок вызова.
     * @param array{prepared: array<string, mixed>, priced: array<string, mixed>} $planned Подготовка и цена.
     * @param list<array<string, mixed>> $operations Операции листа.
     *
     * @return int Новая версия.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если операция отвергнута.
     * @throws GameNotFoundException Если листа нет.
     */
    private function writeCall(GameActionPerformCall $call, array $planned, array $operations): int
    {
        return $this->writePrepared(
            $call->game,
            $call->actorKind,
            $call->actorId,
            $call->expectedSheetVersion,
            $operations,
            $call->projectConflict,
            $call->paid ? [] : $planned['priced']['spends'],
        );
    }

    /**
     * Планирует операции листа и проверки прироста.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $prepared Подготовка.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array{roll: array<string, mixed>, text: string}>} Операции.
     */
    private function planOperations(GameRecord $game, array $prepared): array
    {
        $operations = $prepared['operations'];
        if ($operations === []) {
            return ['operations' => [], 'rolls' => []];
        }

        $preview = $this->ports->sheetWrites->previewDocument($game, $prepared['document'], $operations);
        $planned = $this->ports->reactions->plan(
            $game,
            $prepared['document']['sheet'],
            $preview['rulesRevision'],
            $preview['states'],
            $operations,
        );

        return ['operations' => $planned['operations'], 'rolls' => $planned['rolls']];
    }

    /**
     * Применяет cost-эффекты к подготовленным тратам.
     *
     * @param array<string, mixed> $prepared Подготовка.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param array<string, mixed> $pending Ожидание.
     * @param ProcessStep|null $step Шаг процесса.
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
        ?ProcessStep $step = null,
    ): array {
        return $this->price->of(
            $prepared['spends'],
            $prepared['slice'],
            $prepared['document']['choices'],
            $actionRuleCode,
            $actorKind,
            $actorId,
            $pending,
            $step !== null,
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
     * @param ProcessStep|null $step Шаг процесса.
     *
     * @return array{
     *     slice: CharacterRuleSlice,
     *     document: array{
     *         actualVersion: int,
     *         rulesRevision: int,
     *         choices: array<string, mixed>,
     *         sheet: array<string, mixed>
     *     },
     *     spends: array<int, ResourceSpend>,
     *     operations: list<array<string, mixed>>
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
        ?ProcessStep $step = null,
    ): array {
        $slice = $this->ports->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $spec = $this->ports->cards->assertServerPerformable($slice, $actionRuleCode);
        $this->ports->availability->assertAvailable($game, $actorKind, $actorId, $actionRuleCode);
        $document = $this->document->of($game, $actorKind, $actorId);
        $chosen = $this->chosen->resolve($slice, $spec, $document['sheet'], $allowZeroChosen);
        $chosen = array_replace($chosen, $chosenAmounts);
        $spends = $this->resolveSpends($game, $actionRuleCode, $document, $chosen, $allowZeroChosen);
        if ($step !== null) {
            $spends = (new GameProcessRules())->replaceCosts($spends, $step);
        }

        return [
            'slice' => $slice,
            'document' => $document,
            'spends' => $spends,
            'operations' => $this->states->operations($slice, $document['sheet'], $spec),
        ];
    }

    /**
     * Закрывает ожидание и собирает итог.
     *
     * @param array{prepared: array<string, mixed>, priced: array<string, mixed>, sheetVersion: int, rolls: list<array{roll: array<string, mixed>, text: string}>} $executed Итог.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     sheetVersion: int,
     *     spent: int,
     *     amounts: array<string, int>,
     *     rolls: list<array{roll: array<string, mixed>, text: string}>
     * } Итог.
     */
    private function finish(
        array $executed,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
    ): array {
        $priced = $executed['priced'];
        $closed = $this->ports->pendingCosts->complete(
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
            'rolls' => $executed['rolls'],
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
        return $this->ports->rules->spends->resolveResourceSpends(
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
     * Пишет лист актора по цене действия.
     *
     * @param GameRecord $game Игра.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param int $expectedSheetVersion Версия листа.
     * @param list<array<string, mixed>> $operations Операции листа.
     * @param Closure|null $projectConflict Проектор конфликта.
     * @param array<int, ResourceSpend> $spends Траты.
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
        array $operations,
        ?Closure $projectConflict,
        array $spends = [],
    ): int {
        return $this->ports->sheetWrites->write(
            $game,
            $actorKind,
            $actorId,
            $expectedSheetVersion,
            $operations,
            ['type' => $actorKind, 'id' => $actorId],
            $projectConflict,
            $spends,
        );
    }
}
