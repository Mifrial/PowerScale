<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Исполнение не-атакующего действия: долг, запись и итог.
 */
final class GameActionApply
{
    /**
     * Создаёт сценарий.
     *
     * @param GameActionPorts $ports Порты команды.
     * @param GameActionInput $input Разбор тела.
     * @param GameActionBattle $battle Бой.
     * @param GameActionValue $value Значения строк.
     * @param GameActionDebtStep $debt Шаг долга.
     * @param GameActionOutcome $outcome Исполнение и итог.
     * @param GameActionManeuver $maneuver Манёвр после удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPorts $ports,
        private readonly GameActionInput $input,
        private readonly GameActionBattle $battle,
        private readonly GameActionValue $value,
        private readonly GameActionDebtStep $debt,
        private readonly GameActionOutcome $outcome,
        private readonly GameActionManeuver $maneuver,
    ) {
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
     * @throws GameInvalidException Если команда отвергнута.
     */
    public function act(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        $input = $this->input->input($body);
        $process = $this->prepareProcess($game, $actorUserId, $editAll, $input);
        $followUp = $this->followUp($game, $input, $process);
        $battle = $this->battle->battleFor($game, $actorUserId, $editAll, $input, $followUp);
        $applied = $this->apply($game, $input, $battle, $actorUserId, $viewAll, $followUp, $process);
        if ($applied['debt'] !== true) {
            $this->outcome->post($game, $actorUserId, $input, $applied, $battle);
        }

        return $this->outcome->result($battle, $input, $applied, $followUp);
    }

    /**
     * Исполняет действие после доплаты долга.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param array<string, mixed> $body Сохранённое объявление.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     */
    public function resume(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        int $expectedBattle,
        int $expectedSheet,
    ): array {
        $input = $this->input->input($body);
        $input['expectedBattle'] = $expectedBattle;
        $input['expectedSheet'] = $expectedSheet;
        $process = $this->prepareProcess($game, $actorUserId, $editAll, $input);
        $followUp = $this->followUp($game, $input, $process);
        $battle = $this->battle->battleFor($game, $actorUserId, $editAll, $input, $followUp);
        $applied = $this->apply($game, $input, $battle, $actorUserId, $viewAll, $followUp, $process, true);
        $this->outcome->post($game, $actorUserId, $input, $applied, $battle);

        return array_merge(
            $this->outcome->result($battle, $input, $applied, $followUp),
            ['kind' => 'action', 'phase' => 'declare', 'battleId' => $battle->getId()],
        );
    }

    /**
     * Готовит процесс шага действия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param array<string, mixed> $input Вход.
     *
     * @return array<string, mixed>|null Процесс или null.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function prepareProcess(GameRecord $game, int $actorUserId, bool $editAll, array &$input): ?array
    {
        if ($input['processRuleCode'] === null) {
            return null;
        }

        $battle = $this->battle->battleFor($game, $actorUserId, $editAll, $input, false);
        $process = $this->ports->processes->prepare(
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

        return $process;
    }

    /**
     * Нужна ли подготовка после прошлого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed>|null $process Процесс.
     *
     * @return bool Нужна подготовка.
     *
     * @throws GameInvalidException Если цель не указана.
     */
    private function followUp(GameRecord $game, array $input, ?array $process): bool
    {
        $followUp = $process === null && $this->ports->perform->requiresFollowUp($game, $input['actionRuleCode']);
        if ($followUp && $input['targetKey'] === null) {
            throw new GameInvalidException('Game action target is missing');
        }

        return $followUp;
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
     * @param array<string, mixed>|null $process Процесс.
     * @param bool $paid Долг уже оплачен.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
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
        $slice = $this->ports->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $step = $this->outcome->stepOf($process);
        $descriptive = $step !== null && $step->getExecution()->isNone();
        if ($this->maneuver->isManeuver($game, $input['actionRuleCode'])) {
            if ($process !== null || $step !== null) {
                throw new GameInvalidException('Game strike maneuver cannot be a process step');
            }

            $this->maneuver->assert($game, $input, $battle->getPendingActionEffects());
        }

        $state = $this->quoteState($game, $battle, $input, $step, $descriptive);
        if (!$paid) {
            $state = $this->debt->state($game, $slice, $battle, $input, $followUp, $step, $descriptive, $state);
            if ($state['debt'] !== null) {
                return $state['debt'];
            }
        }

        return $descriptive
            ? $this->descriptiveApply($game, $input, $battle, $process, $step, $state, $paid)
            : $this->performedApply($game, $input, $battle, $actorUserId, $viewAll, $followUp, $process, $step, $paid);
    }

    /**
     * Начальный расчёт шага.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $input Вход.
     * @param ProcessStep|null $step Шаг.
     * @param bool $descriptive Описательный шаг.
     *
     * @return array<string, mixed> Состояние.
     */
    private function quoteState(
        GameRecord $game,
        GameBattleRecord $battle,
        array $input,
        ?ProcessStep $step,
        bool $descriptive,
    ): array {
        $quoted = null;
        $priced = null;
        if ($descriptive) {
            $quoted = $this->ports->perform->quoteStep(
                $game,
                $input['actor']['type'],
                $input['actor']['id'],
                $step,
            );
            $priced = [
                'pending' => $battle->getPendingActionEffects(),
                'amounts' => $this->value->amountsOf($quoted['spends']),
            ];
        }

        return ['quoted' => $quoted, 'priced' => $priced, 'planned' => null, 'debt' => null];
    }

    /**
     * Описательный шаг процесса.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed>|null $process Процесс.
     * @param ProcessStep $step Шаг.
     * @param array<string, mixed> $state Состояние.
     * @param bool $paid Долг уже оплачен.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function descriptiveApply(
        GameRecord $game,
        array $input,
        GameBattleRecord $battle,
        ?array $process,
        ProcessStep $step,
        array $state,
        bool $paid,
    ): array {
        $spends = $paid ? $state['quoted']['spends'] : $state['planned']['spends'];
        $sheetVersion = $this->descriptiveSpend($game, $input, $state, $spends, $paid);
        $pending = $this->completeProcess($game, $input, $battle, $process, $step, $battle->getPendingActionEffects());
        $stored = $this->outcome->store($input, $pending, null);

        return [
            'debt' => false,
            'pending' => $stored['pending'],
            'performed' => [
                'pending' => $stored['pending'],
                'sheetVersion' => $sheetVersion,
                'spent' => $this->value->spentOf($spends),
                'amounts' => $state['priced']['amounts'],
                'rolls' => [],
            ],
            'version' => $stored['version'],
            'reaction' => null,
        ];
    }

    /**
     * Списание описательного шага.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $state Состояние.
     * @param array<int, mixed> $spends Траты.
     * @param bool $paid Долг уже оплачен.
     *
     * @return int Версия листа.
     *
     * @throws GameInvalidException Если списание чужое.
     */
    private function descriptiveSpend(
        GameRecord $game,
        array $input,
        array $state,
        array $spends,
        bool $paid,
    ): int {
        if ($paid) {
            return $input['expectedSheet'];
        }

        $this->ports->perform->assertSpends($game, $state['quoted']['document']['sheet'], $spends);

        return $this->ports->perform->spend(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['expectedSheet'],
            $spends,
        );
    }

    /**
     * Обычный шаг и подготовка.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param int $actorUserId Пользователь.
     * @param bool $viewAll Полный просмотр.
     * @param bool $followUp Нужна подготовка.
     * @param array<string, mixed>|null $process Процесс.
     * @param ProcessStep|null $step Шаг.
     * @param bool $paid Долг уже оплачен.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     */
    private function performedApply(
        GameRecord $game,
        array $input,
        GameBattleRecord $battle,
        int $actorUserId,
        bool $viewAll,
        bool $followUp,
        ?array $process,
        ?ProcessStep $step,
        bool $paid,
    ): array {
        $reaction = $this->reaction($game, $input, $battle, $followUp);
        $before = $battle->getPendingActionEffects();
        $performed = $this->outcome->perform(
            $game,
            $input,
            $before,
            $actorUserId,
            $viewAll,
            !$followUp,
            $step,
            $paid,
        );
        if ($this->maneuver->isManeuver($game, $input['actionRuleCode'])) {
            $applied = $this->maneuver->apply($game, $input, $before);
            $performed['strikeManeuver'] = $applied;
            $performed['rolls'] = [...($performed['rolls'] ?? []), ...($applied['rolls'] ?? [])];
        }

        $performed = $this->completePerformed($game, $input, $battle, $process, $step, $performed);

        return $this->performedResult($input, $performed, $reaction);
    }

    /**
     * Завершает шаг процесса после исполнения.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed>|null $process Процесс.
     * @param ProcessStep|null $step Шаг.
     * @param array<string, mixed> $performed Итог сценария.
     *
     * @return array<string, mixed> Итог сценария.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function completePerformed(
        GameRecord $game,
        array $input,
        GameBattleRecord $battle,
        ?array $process,
        ?ProcessStep $step,
        array $performed,
    ): array {
        if (is_array($process) && $step !== null) {
            $performed['pending'] = $this->completeProcess(
                $game,
                $input,
                $battle,
                $process,
                $step,
                $performed['pending'],
            );
        }

        return $performed;
    }

    /**
     * Итог исполненного действия.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $performed Итог сценария.
     * @param string|null $reaction Реакция.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия другая.
     */
    private function performedResult(array $input, array $performed, ?string $reaction): array
    {
        $stored = $this->outcome->store($input, $performed['pending'], $reaction);

        return [
            'debt' => false,
            'pending' => $stored['pending'],
            'performed' => $performed,
            'version' => $stored['version'],
            'reaction' => $reaction,
        ];
    }

    /**
     * Реакция цели на подготовку.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param bool $followUp Нужна подготовка.
     *
     * @return string|null Реакция.
     */
    private function reaction(GameRecord $game, array $input, GameBattleRecord $battle, bool $followUp): ?string
    {
        if (!$followUp) {
            return null;
        }

        if (!$this->ports->perform->requiresPreviousAttack($game, $input['actionRuleCode'])) {
            return null;
        }

        return $this->ports->perform->followUpReaction(
            $game,
            $input['actionRuleCode'],
            $battle->getPendingActionEffects(),
            $input['actor']['type'],
            $input['actor']['id'],
            (string) $input['targetKey'],
        );
    }

    /**
     * Открывает и завершает шаг процесса.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed>|null $process Процесс.
     * @param ProcessStep $step Шаг.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array<string, mixed> Ожидание.
     *
     * @throws GameInvalidException Если шаг чужой.
     */
    private function completeProcess(
        GameRecord $game,
        array $input,
        GameBattleRecord $battle,
        ?array $process,
        ProcessStep $step,
        array $pending,
    ): array {
        $processId = $this->ports->processes->begin($battle, $input['actor'], $process);

        return $this->ports->processes->complete(
            $game,
            $input['actor'],
            $processId,
            $step->getCode(),
            true,
            $pending,
        );
    }
}
