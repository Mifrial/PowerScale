<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Запись листа, чат и сохранение одиночного удара.
 */
final class GameStrikeSingleWrite
{
    /**
     * Создаёт запись удара.
     *
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameStrikeSingleChat $chat Чат удара.
     * @param GameActionRepository $actions Действия.
     * @param GameBattleRepository $battles Бои.
     * @param GameStrikeSingleTrail $trail Завершение действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSingleChat $chat,
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeSingleTrail $trail,
    ) {
    }

    /**
     * Пишет лист и чат, закрывает удар и сохраняет ожидание.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed> Итог.
     */
    public function finish(GameStrikeSingleRun $run): array
    {
        $this->writeSheet($run);
        $this->post($run);
        $this->answer($run);
        $this->marks($run);
        if ($this->remaining($run) > 0) {
            return $this->partial($run);
        }

        if ($run->sequential) {
            $this->actions->close($run->strikeId);
        }

        $trail = $this->trail->apply($run, $run->pending);
        if ($trail['modifiers'] !== null) {
            $run->result['modifiers'] = $trail['modifiers'];
        }

        $this->replace($run, $this->store($run, $trail['pending']));
        $run->result['closed'] = true;
        $run->result['sequential'] = $run->sequential;
        $run->result['resolvedIndex'] = $run->activeIndex;

        return $run->result;
    }

    /**
     * Пишет лист цели с уроном и тратами.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function writeSheet(GameStrikeSingleRun $run): void
    {
        $noEffects = $run->isPush
            ? $run->prepared === null || $run->prepared['operations'] === []
            : $run->autoFail;
        if ($noEffects && $run->spends === []) {
            $run->result['sheetVersion'] = null;

            return;
        }

        $run->result['sheetVersion'] = $noEffects
            ? $this->writeResources($run)
            : $this->write($run);
    }

    /**
     * Записывает только траты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return int Версия листа.
     */
    private function writeResources(GameStrikeSingleRun $run): int
    {
        return $this->sheetWrites->writeResources(
            $run->input->game,
            $run->target['type'],
            $run->target['id'],
            $run->input->expectedSheetVersion,
            $run->spends,
            $run->target,
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->conflict($run, $exception),
        );
    }

    /**
     * Записывает операции листа и траты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return int Версия листа.
     */
    private function write(GameStrikeSingleRun $run): int
    {
        return $this->sheetWrites->write(
            $run->input->game,
            $run->target['type'],
            $run->target['id'],
            $run->input->expectedSheetVersion,
            $run->prepared['operations'] ?? [],
            $run->target,
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->conflict($run, $exception),
            $run->spends,
        );
    }

    /**
     * Проекция конфликта записи листа.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param CharacterConflictException|GameEconomyConflictException $exception Конфликт.
     *
     * @return array<string, mixed> Ответ.
     */
    private function conflict(GameStrikeSingleRun $run, CharacterConflictException|GameEconomyConflictException $exception): array
    {
        return $this->sheets->conflicts->projectWriteConflict(
            $run->input->game,
            $run->target,
            $exception,
            $run->scope->actorUserId,
            $run->scope->viewAll,
        );
    }

    /**
     * Пишет сообщение удара и пакеты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function post(GameStrikeSingleRun $run): void
    {
        $targetOpen = $run->targetOpen;
        $targetOpen['conditions'] = $run->conditions;
        $maneuver = $run->maneuver !== [] ? ['ruleCode' => is_string($run->maneuver['ruleCode'] ?? null) ? $run->maneuver['ruleCode'] : null] : null;
        $this->chat->post(
            $run->input->game,
            $run->battle,
            $targetOpen,
            $run->scope->actorUserId,
            $run->strikeId,
            $run->choice['reaction'],
            $run->result,
            null,
            $run->coverInfo,
            $maneuver,
        );
        if ($run->pushChecks !== []) {
            $this->chat->postChecks(
                $run->input->game,
                $run->battle,
                $targetOpen,
                $run->scope->actorUserId,
                $run->strikeId,
                $run->pushChecks,
            );
        }

        $this->packets($run, $targetOpen);
    }

    /**
     * Пишет сообщения дополнительных пакетов.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $targetOpen Строка цели.
     *
     * @return void
     */
    private function packets(GameStrikeSingleRun $run, array $targetOpen): void
    {
        for ($packet = 2; $packet <= $run->packets; ++$packet) {
            $packetResult = $run->result;
            $packetResult['injury'] = $run->result['packetInjuries'][$packet - 1] ?? $run->result['injury'];
            $packetResult['damage'] = $run->result['packetDamages'][$packet - 1] ?? $run->result['damage'];
            $this->chat->postPacket(
                $run->input->game,
                $run->battle,
                $targetOpen,
                $run->scope->actorUserId,
                $run->strikeId,
                $packet,
                $packetResult,
            );
        }
    }

    /**
     * Записывает ответ защиты в строку удара.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function answer(GameStrikeSingleRun $run): void
    {
        $answer = [
            'reaction' => $run->choice['reaction'],
            'block_item_inventory_id' => $run->choice['blockItemInventoryId'],
            'block_item_profile_index' => $run->choice['blockItemProfileIndex'],
            'block_item_rule_code' => $run->choice['blockItemRuleCode'],
            'conditions' => $run->conditions,
            'resolution' => [],
        ];
        if ($run->sequential) {
            $this->actions->answerStrike(is_int($run->row['id'] ?? null) ? $run->row['id'] : 0, $answer);

            return;
        }

        $this->actions->closeWithAnswers($run->strikeId, $answer);
    }

    /**
     * Снимает и ставит метки боя.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function marks(GameStrikeSingleRun $run): void
    {
        $marks = new GameBattleMarks();
        $marksAfter = $run->markKeys === [] ? null : $marks->consume($run->battle->getMarks(), $run->markKeys);
        if ($run->maneuverMarks !== []) {
            $base = $marksAfter ?? $run->battle->getMarks();
            foreach ($run->maneuverMarks as $subject) {
                $base = $this->maneuverMarks($run, $base, $subject, $marks);
            }

            $marksAfter = $base;
        }

        $run->marksAfter = $marksAfter;
    }

    /**
     * Ставит метки манёвра для одного защитника.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<int, array<string, mixed>> $base Метки боя.
     * @param array{type: string, id: int} $subject Защитник.
     * @param GameBattleMarks $marks Метки.
     *
     * @return array<int, array<string, mixed>> Метки.
     */
    private function maneuverMarks(GameStrikeSingleRun $run, array $base, array $subject, GameBattleMarks $marks): array
    {
        foreach (is_array($run->maneuver['marks'] ?? null) ? $run->maneuver['marks'] : [] as $mark) {
            if (!is_array($mark)) {
                continue;
            }

            $base = $marks->upsert(
                $base,
                $this->maneuverMark($subject, $run->attackerKind, $run->attackerId, $run->maneuver, $mark),
            );
        }

        return $base;
    }

    /**
     * Метка манёвра для защитника.
     *
     * @param array{type: string, id: int} $subject Защитник.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     * @param array<string, mixed> $maneuver Описание манёвра.
     * @param array<string, mixed> $mark Описание метки.
     *
     * @return array<string, mixed> Метка боя.
     */
    private function maneuverMark(
        array $subject,
        string $attackerKind,
        int $attackerId,
        array $maneuver,
        array $mark,
    ): array {
        return [
            'subject' => ['type' => $subject['type'], 'id' => $subject['id']],
            'kind' => is_string($mark['kind'] ?? null) ? $mark['kind'] : 'advantage',
            'value' => is_int($mark['value'] ?? null) ? $mark['value'] : 0,
            'source_code' => is_string($mark['source_code'] ?? null) ? $mark['source_code'] : 'circumstances',
            'source_rule_code' => is_string($maneuver['ruleCode'] ?? null) ? $maneuver['ruleCode'] : null,
            'scope' => ['defense_against' => ['type' => $attackerKind, 'id' => $attackerId]],
            'consumes' => 'first_defense',
            'expires' => ['kind' => 'turn', 'of' => ['type' => $attackerKind, 'id' => $attackerId]],
        ];
    }

    /**
     * Сколько ударов ещё ждут ответа.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return int Число.
     */
    private function remaining(GameStrikeSingleRun $run): int
    {
        if (!$run->sequential) {
            return 0;
        }

        $remaining = 0;
        foreach ($this->actions->getStrikes($run->strikeId) as $index => $row) {
            $answered = $index === $run->activeIndex ? $run->choice['reaction'] : ($row['reaction'] ?? null);
            if ($answered === null) {
                ++$remaining;
            }
        }

        return $remaining;
    }

    /**
     * Сохраняет частичный последовательный удар.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed> Итог.
     */
    private function partial(GameStrikeSingleRun $run): array
    {
        $this->replace($run, $this->store($run, $run->pending));
        $run->result['closed'] = false;
        $run->result['sequential'] = true;
        $run->result['resolvedIndex'] = $run->activeIndex;

        return $run->result;
    }

    /**
     * Сохраняет снимок последнего удара в ожидании.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return array<string, mixed> Ожидание.
     */
    private function store(GameStrikeSingleRun $run, array $pending): array
    {
        $snapshot = new GameLastStrikeSnapshot();

        return $snapshot->store(
            $pending,
            $run->attackerKind,
            $run->attackerId,
            $run->actionRuleCode,
            $snapshot->kindOf($run->slice, $run->actionRuleCode),
            [
                [
                    'targetKey' => $run->targetKey,
                    'attackSr' => $run->result['success'],
                    'reaction' => $run->choice['reaction'],
                    'damaged' => ($run->injury?->toInteger() ?? 0) > 0,
                    'itemInventoryId' => $run->itemInventoryId,
                    'itemRuleCode' => $run->itemRuleCode,
                    'profileType' => $run->profileType,
                ],
            ],
        );
    }

    /**
     * Записывает ожидание и метки в бой.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $pending Ожидание.
     *
     * @return void
     */
    private function replace(GameStrikeSingleRun $run, array $pending): void
    {
        if ($run->defenderToken !== null) {
            $this->battles->replaceTracked(
                $run->input->battleId,
                $run->input->expectedBattleVersion,
                $pending,
                null,
                null,
                null,
                $run->defenderToken['cycle'],
                $run->marksAfter,
            );

            return;
        }

        $this->battles->replacePending(
            $run->input->battleId,
            $run->input->expectedBattleVersion,
            $pending,
            $run->marksAfter,
        );
    }
}
