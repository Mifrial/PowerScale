<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeOpenRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Запись открытого удара: списание, долг, строка и цели.
 */
final class GameStrikeOpen
{
    /**
     * Создаёт запись открытия.
     *
     * @param GameStrikeOpenProcess $process Шаг процесса.
     * @param GameStrikeOpenView $view Снимок и жетон.
     * @param GameStrikeOpenRows $rows Строки удара.
     * @param GameStrikeOpenLedger $ledger Списание и запись.
     * @param GameStrikeOpenPaid $paid Оплаченный удар.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeOpenProcess $process,
        private readonly GameStrikeOpenView $view,
        private readonly GameStrikeOpenRows $rows,
        private readonly GameStrikeOpenLedger $ledger,
        private readonly GameStrikeOpenPaid $paid,
    ) {
    }

    /**
     * Открывает удар через общий контур.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     * @param string $lane Полоса долга.
     * @param string $subject Подлежащее.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если выбор или строка невалидны.
     */
    public function openAction(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        string $kind,
        string $lane,
        string $subject,
    ): array {
        $this->view->assertFollowUp($game, $battle, $choice);
        $snapshot = $this->view->snapshot($game, $choice);
        $choice['tokenSpendInfo'] = $this->view->tokenInfo($game, $choice, $snapshot);
        $row = $this->rows->action($battle, $sessionId, $choice, $kind);
        $run = $this->run($game, $sessionId, $battle, $expectedBattleVersion, $choice, $snapshot, $kind, $lane, $subject);
        $run->action = $row['action'];
        $run->targets = $row['strikes'];
        $opened = $this->open($run);

        return $this->result($battle, $kind, $opened);
    }

    /**
     * Открывает удар, когда F1 уже уплачены.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     * @param string $kind Вид удара.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если выбор или строка невалидны.
     */
    public function openPaidAction(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        string $kind,
    ): array {
        $this->view->assertFollowUp($game, $battle, $choice);
        $context = $this->process->context($game, $battle, $choice);
        $snapshot = $this->view->snapshot($game, $choice);
        $run = $this->run($game, $sessionId, $battle, $expectedBattleVersion, $choice, $snapshot, $kind);
        [$run->attackerKind, $run->attackerId] = $this->attacker($choice);
        $run->context = $context;
        $written = $this->paid->paid($run);

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $written['strikeId'],
            'version' => $written['version'],
        ];
    }

    /**
     * Списывает стоимость и пишет открытый удар или удержанный долг.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     *
     * @return array{debt: bool, strikeId: int, version: int, spent: int} Итог.
     *
     * @throws GameInvalidException Если выбор, строка или ресурс невалидны.
     */
    private function open(GameStrikeOpenRun $run): array
    {
        $this->prepare($run);

        return $this->ledger->open($run);
    }

    /**
     * Проверяет атакующего, процесс и добивание.
     *
     * @param GameStrikeOpenRun $run Контекст записи.
     *
     * @return void
     *
     * @throws GameInvalidException Если выбор невалиден.
     */
    private function prepare(GameStrikeOpenRun $run): void
    {
        [$run->attackerKind, $run->attackerId] = $this->attacker($run->choice);
        if (($run->choice['tokenSpend'] ?? null) !== null && count($run->targets) > 1) {
            throw new GameInvalidException('Game concentration supports a single strike');
        }

        $run->context = $this->process->context($run->game, $run->battle, $run->choice);
        $actionCode = $run->context !== null
            ? $run->context['actionCode']
            : (is_string($run->action['action_rule_code'] ?? null) ? $run->action['action_rule_code'] : '');
        $run->action['action_rule_code'] = $actionCode;
        $run->closingProcessId = $run->context === null
            ? $this->process->closing($run->game, $run->battle, $run->choice, $actionCode, $run->snapshot)
            : null;
    }

    /**
     * Контекст записи из аргументов вызова.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $snapshot Снимок.
     * @param string $kind Вид удара.
     * @param string $lane Полоса долга.
     * @param string $subject Подлежащее.
     *
     * @return GameStrikeOpenRun Контекст.
     */
    private function run(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        array $snapshot,
        string $kind,
        string $lane = '',
        string $subject = '',
    ): GameStrikeOpenRun {
        $run = new GameStrikeOpenRun();
        $run->game = $game;
        $run->sessionId = $sessionId;
        $run->battle = $battle;
        $run->expectedBattleVersion = $expectedBattleVersion;
        $run->choice = $choice;
        $run->snapshot = $snapshot;
        $run->kind = $kind;
        $run->lane = $lane;
        $run->subject = $subject;

        return $run;
    }

    /**
     * Итог открытия для HTTP-контура.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $kind Вид удара.
     * @param array{debt: bool, strikeId: int, version: int, spent: int} $opened Итог записи.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(GameBattleRecord $battle, string $kind, array $opened): array
    {
        $result = [
            'battleId' => $battle->getId(),
            'strikeId' => $opened['strikeId'],
            'version' => $opened['version'],
            'spent' => $opened['spent'],
        ];
        if ($kind === 'wide_strike') {
            $result['targetResults'] = [];
        } else {
            $result['sheetVersion'] = null;
        }

        if ($opened['debt']) {
            $result['debt'] = true;
        }

        return $result;
    }

    /**
     * Атакующий объявления.
     *
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array{0: string, 1: int} Вид и id.
     *
     * @throws GameInvalidException Если атакующий невалиден.
     */
    private function attacker(array $choice): array
    {
        $attacker = $choice['attacker'] ?? null;
        $kind = is_array($attacker) ? ($attacker['type'] ?? null) : null;
        $id = is_array($attacker) ? ($attacker['id'] ?? null) : null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return [$kind, $id];
    }
}
