<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeOpenRun;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Списание стоимости и запись открытого удара.
 */
final class GameStrikeOpenLedger
{
    /**
     * Создаёт запись открытия.
     *
     * @param GameStrikeOpenStore $store Запись боя и действия.
     * @param GameStrikeSpend $spend Списание атакующего.
     * @param GameStrikeOpenProcess $process Шаг процесса.
     * @param GameStrikeOpenTargets $targets Проверка строк.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeOpenStore $store,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeOpenProcess $process,
        private readonly GameStrikeOpenTargets $targets,
    ) {
    }

    /**
     * Списывает стоимость и пишет открытый удар или удержанный долг.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     *
     * @return array{debt: bool, strikeId: int, version: int} Итог.
     *
     * @throws GameInvalidException Если ресурс или строка невалидны.
     * @throws GameNotFoundException Если revision или NPC отсутствует.
     * @throws GameBattleConflictException Если CAS атакующего устарел.
     */
    public function open(GameStrikeOpenRun $run): array
    {
        $this->targets->assert(
            $run->game,
            $run->choice,
            $run->snapshot,
            $run->targets,
            $run->action['action_rule_code'],
            $run->attackerKind,
            $run->attackerId,
        );
        $hold = $this->spend->spendAttacker(
            $run->game,
            $run->choice,
            $run->battle,
            $run->snapshot,
            $run->lane,
            $run->subject,
        );
        $run->choice['tokenSpendInfo'] = $hold['tokenSpend'];
        if ($hold['debtHold'] !== null) {
            return $this->debt($run, $hold);
        }

        return $this->writeStrike($run, $hold['pending']);
    }

    /**
     * Проставляет шаг процесса в строку действия и цели.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     * @param array<string, mixed> $action Строка действия.
     * @param list<array<string, mixed>> $targets Строки.
     * @param array<string, mixed>|null $context Контекст шага.
     * @param int|null $closingProcessId Открытый процесс защиты.
     *
     * @return array{action: array<string, mixed>, strikes: list<array<string, mixed>>} Строки.
     */
    public function attachProcess(
        GameStrikeOpenRun $run,
        array $action,
        array $targets,
        ?array $context,
        ?int $closingProcessId,
    ): array {
        if ($context !== null) {
            $action['process_id'] = $this->process->begin($run->battle, $run->attackerKind, $run->attackerId, $context);
            $action['process_step_code'] = $context['processStepCode'];
            $targets = $this->process->withRepeats($targets, $run->choice);
        } elseif ($closingProcessId !== null) {
            $action['process_id'] = $closingProcessId;
            $action['process_step_code'] = '';
        }

        return ['action' => $action, 'strikes' => $targets];
    }

    /**
     * Удерживает долг вместо строки удара.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     * @param array<string, mixed> $hold Результат списания.
     *
     * @return array{debt: bool, strikeId: int, version: int} Итог.
     *
     * @throws GameBattleConflictException Если CAS атакующего устарел.
     * @throws GameInvalidException Если поле боя невалидно.
     */
    private function debt(GameStrikeOpenRun $run, array $hold): array
    {
        $saved = $this->store->replaceDebt(
            $run->battle->getId(),
            $run->expectedBattleVersion,
            $hold['pending'],
            $hold['debtHold'],
            $hold['cycle'],
        );

        return ['debt' => true, 'strikeId' => 0, 'version' => $saved->getStateVersion()];
    }

    /**
     * Пишет строку действия и ожидание боя.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array{debt: bool, strikeId: int, version: int} Итог.
     *
     * @throws GameBattleConflictException Если CAS боя устарел.
     * @throws GameInvalidException Если строка действия невалидна.
     */
    private function writeStrike(GameStrikeOpenRun $run, array $pending): array
    {
        $prepared = $this->attachProcess(
            $run,
            $run->action,
            $run->targets,
            $run->context,
            $run->closingProcessId,
        );
        $strikeId = $this->store->addStrikes($prepared['action'], $prepared['strikes']);
        $this->store->replacePending($run->battle->getId(), $run->expectedBattleVersion, $pending);

        return ['debt' => false, 'strikeId' => $strikeId, 'version' => $run->expectedBattleVersion + 1];
    }
}
