<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Подготовка, проверка, отложенный каст и запись — одна причина изменения.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Проверка сотворения и эффект после удара касания.
 */
final class GameSpellTouchFinish
{
    /**
     * Создаёт завершение касания.
     *
     * @param GameSpellTouchFacts $facts Подготовка заклинания.
     * @param GameSpellCastCheck $check Сложность и бросок.
     * @param GameSpellCastDamage $damage Урон.
     * @param GameStrikeSheetWrites $writes Запись листа.
     * @param GameSpellCastChat $chat Чат.
     * @param GameSpellCastSaturation $saturation Насыщение.
     * @param GameSpellCastNextCast $nextCast Хвост Сложности.
     * @param GameSpellCastPending $pending Отложенный каст.
     * @param GameActionRepository $actions Открытое действие.
     * @param GameSpellDeviation $deviation Отклонение.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellTouchFacts $facts,
        private readonly GameSpellCastCheck $check,
        private readonly GameSpellCastDamage $damage,
        private readonly GameStrikeSheetWrites $writes,
        private readonly GameSpellCastChat $chat,
        private readonly GameSpellCastSaturation $saturation,
        private readonly GameSpellCastNextCast $nextCast,
        private readonly GameSpellCastPending $pending,
        private readonly GameActionRepository $actions,
        private readonly GameSpellDeviation $deviation,
    ) {
    }

    /**
     * Досчитывает каст после попадания или промаха касания.
     *
     * @param GameRecord $game Игра.
     * @param GameStrikeSingleRun $run Удар.
     * @param int $targetVersion Версия листа цели после удара.
     *
     * @return void
     *
     * @throws GameInvalidException Если заклинание или лист битые.
     */
    public function finish(GameRecord $game, GameStrikeSingleRun $run, int $targetVersion): void
    {
        $payload = $run->row['spell_cast'] ?? null;
        if (!is_array($payload) || $payload === []) {
            return;
        }

        $choice = [
            'attacker' => ['type' => $run->attackerKind, 'id' => $run->attackerId],
            'defender' => $run->target,
        ];
        $prepared = $this->facts->prepared($game, $choice, $payload);
        $actorKey = $prepared->casterKind . ':' . $prepared->casterId;
        $trainingDelta = $this->nextCast->peek($run->pending, $actorKey, $prepared->spent);
        $check = $this->check->check($game, $prepared, $trainingDelta);
        $attackSr = is_int($run->result['success'] ?? null) ? $run->result['success'] : 0;
        $plan = $this->saturation->plan($prepared->saturationSource, $check);
        $deviation = $this->deviation->afterCheck($prepared, $check);
        if ($plan->hasChoice() || $this->deviation->needsPause($deviation)) {
            $this->defer($run, $prepared, $payload, $check, $trainingDelta, $attackSr, $targetVersion, $plan, $deviation);

            return;
        }

        $saturation = $this->saturation->apply($plan, 0);
        $damage = $this->damage->apply($game, $prepared, $check['castOk'], $attackSr, $saturation->getPowerDelta());
        $damage['milk'] = $attackSr <= 0;

        $casterOperations = $this->casterOperations($prepared, $deviation);
        $sameSheet = $run->target['type'] === $prepared->casterKind
            && $run->target['id'] === $prepared->casterId;
        if ($sameSheet) {
            $damage['operations'] = [...$damage['operations'], ...$casterOperations];
            $casterOperations = [];
        }

        $version = $targetVersion;
        if ($damage['operations'] !== []) {
            $version = $this->writes->write(
                $game,
                $run->target['type'],
                $run->target['id'],
                $targetVersion,
                $damage['operations'],
                $run->target,
            );
        }

        $casterVersion = $prepared->casterVersion;
        if ($casterOperations !== []) {
            $casterVersion = $this->writes->write(
                $game,
                $prepared->casterKind,
                $prepared->casterId,
                $casterVersion,
                $casterOperations,
            );
        }

        $run->pending = $this->nextCast->consume($run->pending, $actorKey);
        $run->pending = $this->nextCast->queue(
            $run->pending,
            $actorKey,
            $prepared->nextCastSources,
            $prepared->spent,
            $saturation->getRemainingRating(),
        );
        $thread = GameCombatThread::buildSpell($run->battle, $run->strikeId);
        $this->chat->post(
            $game,
            $run->scope->actorUserId,
            $prepared,
            $run->battle,
            $check,
            $damage,
            false,
            $thread,
            $saturation,
            $this->nextCast->rows($run->pending, $actorKey),
            $deviation,
        );
        $this->setResult($run, $check, $damage, $version, null, $casterVersion);
    }

    /**
     * Откладывает эффект и открывает действие выбора.
     *
     * @param GameStrikeSingleRun $run Удар.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $payload Блок заклинания.
     * @param array<string, mixed> $check Итог проверки.
     * @param int $trainingDelta Сдвиг Сложности.
     * @param int $attackSr Успех касания.
     * @param int $targetVersion Версия цели после удара.
     * @param GameSpellCastSaturationPlan $plan Выбор.
     * @param array<string, mixed>|null $deviation Отклонение.
     *
     * @return void
     *
     * @throws GameInvalidException Если действие или строка битые.
     */
    private function defer(
        GameStrikeSingleRun $run,
        GameSpellCastPrepared $prepared,
        array $payload,
        array $check,
        int $trainingDelta,
        int $attackSr,
        int $targetVersion,
        GameSpellCastSaturationPlan $plan,
        ?array $deviation,
    ): void {
        $castId = 'spell-' . $run->strikeId;
        $actionId = $this->actions->add([
            'kind' => 'spell',
            'battle_id' => $run->battle->getId(),
            'session_id' => $run->battle->getSessionId(),
            'attacker_kind' => $prepared->casterKind,
            'attacker_id' => $prepared->casterId,
            'action_rule_code' => $prepared->spellCode,
            'open' => true,
        ]);
        $pending = $this->pending->build(
            $castId,
            $actionId,
            $run->scope->actorUserId,
            $prepared,
            $payload,
            $check,
            $trainingDelta,
            $attackSr,
            true,
            $run->strikeId,
            GameCombatThread::buildSpell($run->battle, $run->strikeId),
            $plan,
            $deviation,
        );
        $run->pendingSpellCast = $pending;
        $actorKey = $prepared->casterKind . ':' . $prepared->casterId;
        $run->pending = $this->nextCast->consume($run->pending, $actorKey);
        $run->result['spellCast'] = $check;
        $run->result['spellCast']['difficulty'] = $check['difficulty'];
        $run->result['spellCastPhase'] = 'check';
        $run->result['spellSr'] = null;
        $run->result['spellDamage'] = null;
        $run->result['spellInjury'] = null;
        $run->result['spellTargetVersion'] = $targetVersion;
        $run->result['deviation'] = $this->deviation->view($prepared, $deviation);
        $run->result['pendingSpellCast'] = $pending;
    }

    /**
     * Пишет итог заклинания в результат удара.
     *
     * @param GameStrikeSingleRun $run Удар.
     * @param array<string, mixed> $check Проверка.
     * @param array<string, mixed> $damage Урон.
     * @param int $version Версия цели.
     * @param array<string, mixed>|null $pending Отложенный каст.
     * @param int|null $casterVersion Версия листа кастера.
     *
     * @return void
     */
    private function setResult(
        GameStrikeSingleRun $run,
        array $check,
        array $damage,
        int $version,
        ?array $pending,
        ?int $casterVersion = null,
    ): void {
        $run->result['spellCast'] = $check;
        $run->result['spellCast']['difficulty'] = $check['difficulty'];
        $run->result['spellSr'] = $damage['spellSr'];
        $run->result['spellDamage'] = $damage['spellDamage'];
        $run->result['spellInjury'] = $damage['injury'];
        $run->result['spellTargetVersion'] = $version;
        if ($casterVersion !== null) {
            $run->result['casterSheetVersion'] = $casterVersion;
        }

        $run->result['pendingSpellCast'] = $pending;
    }

    /**
     * Операции листа кастера от отклонения.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed>|null $deviation Отклонение.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если карточка отклонения битая.
     */
    private function casterOperations(GameSpellCastPrepared $prepared, ?array $deviation): array
    {
        if ($deviation === null) {
            return [];
        }

        $spec = $this->deviation->spec($prepared->slice, $prepared->path->getCastCheckCode());
        if ($spec === null) {
            return [];
        }

        return $this->deviation->operations(
            $spec,
            $prepared->casterSheet,
            $prepared->sourceKey,
            is_int($deviation['strength'] ?? null) ? $deviation['strength'] : 0,
        );
    }
}
