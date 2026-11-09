<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

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
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellTouchFacts $facts,
        private readonly GameSpellCastCheck $check,
        private readonly GameSpellCastDamage $damage,
        private readonly GameStrikeSheetWrites $writes,
        private readonly GameSpellCastChat $chat,
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
        $check = $this->check->check($game, $prepared);
        $attackSr = is_int($run->result['success'] ?? null) ? $run->result['success'] : 0;
        $damage = $this->damage->apply($game, $prepared, $check['castOk'], $attackSr);
        $damage['milk'] = $attackSr <= 0;

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

        $thread = GameCombatThread::buildSpell($run->battle, $run->strikeId);
        $this->chat->post($game, $run->scope->actorUserId, $prepared, $run->battle, $check, $damage, false, $thread);
        $run->result['spellCast'] = $check;
        $run->result['spellCast']['difficulty'] = $check['difficulty'];
        $run->result['spellSr'] = $damage['spellSr'];
        $run->result['spellDamage'] = $damage['spellDamage'];
        $run->result['spellInjury'] = $damage['injury'];
        $run->result['spellTargetVersion'] = $version;
    }
}
