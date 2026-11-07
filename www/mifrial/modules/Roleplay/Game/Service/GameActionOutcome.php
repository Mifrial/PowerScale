<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;

/**
 * Исполнение сценария действия, сообщение и итог.
 */
final class GameActionOutcome
{
    /**
     * Создаёт сценарий.
     *
     * @param GameActionPorts $ports Порты команды.
     * @param GameActionBattle $battle Бой.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionPorts $ports,
        private readonly GameActionBattle $battle,
    ) {
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
     * @param ProcessStep|null $step Шаг.
     * @param bool $paid Долг уже оплачен.
     *
     * @return array<string, mixed> Итог сценария.
     *
     * @throws GameInvalidException Если действие отвергнуто.
     */
    public function perform(
        GameRecord $game,
        array $input,
        array $pending,
        int $actorUserId,
        bool $viewAll,
        bool $allowZeroChosen,
        ?ProcessStep $step = null,
        bool $paid = false,
    ): array {
        return $this->ports->perform->perform(
            $game,
            $input['actor']['type'],
            $input['actor']['id'],
            $input['actionRuleCode'],
            $input['expectedSheet'],
            $pending,
            $allowZeroChosen,
            $input['chosenAmounts'],
            $this->battle->conflictProjection(
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
    public function post(
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

        $this->ports->chat->post(
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
    public function result(GameBattleRecord $battle, array $input, array $applied, bool $followUp): array
    {
        if ($applied['debt'] === true) {
            return $this->debtResult($battle, $applied);
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
     * Шаг процесса.
     *
     * @param array<string, mixed>|null $process Процесс.
     *
     * @return ProcessStep|null Шаг.
     */
    public function stepOf(?array $process): ?ProcessStep
    {
        if (is_array($process) && ($process['step'] ?? null) instanceof ProcessStep) {
            return $process['step'];
        }

        return null;
    }

    /**
     * Сохраняет ожидание и, если нужно, подготовку.
     *
     * @param array<string, mixed> $input Вход.
     * @param array<string, mixed> $pending Ожидание.
     * @param string|null $reaction Реакция цели.
     *
     * @return array{pending: array<string, mixed>, version: int} Итог.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameBattleConflictException Если версия другая.
     */
    public function store(array $input, array $pending, ?string $reaction): array
    {
        if ($reaction !== null) {
            $pending = $this->ports->prepared->replace(
                $pending,
                $input['actor']['type'],
                $input['actor']['id'],
                (string) $input['targetKey'],
                $reaction,
                $input['actionRuleCode'],
            );
        }

        $battle = $this->ports->battles->replacePending($input['battleId'], $input['expectedBattle'], $pending);

        return ['pending' => $pending, 'version' => $battle->getStateVersion()];
    }

    /**
     * Итог открытого долга.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $applied Итог действия.
     *
     * @return array<string, mixed> Итог.
     */
    private function debtResult(GameBattleRecord $battle, array $applied): array
    {
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




}