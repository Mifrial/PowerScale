import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import { COMBAT_CHAT_ROUND, COMBAT_CHAT_TURN } from '@/modules/Roleplay/Game/Constant/Combat/COMBAT_CHAT_FOLD_KINDS';
import { useCombatChatThread } from '@/modules/Roleplay/Game/Composables/useCombatChatThread';

describe('useCombatChatThread', () => {
  it('liveIds держит текущий и предыдущий раунд/ход', () => {
    const thread = useCombatChatThread(91001);
    thread.clearLive();
    const round1 = thread.beginRound();
    const turn1 = thread.beginTurn();
    const round2 = thread.beginRound();
    const turn2 = thread.beginTurn();

    expect(thread.liveIds.value).toEqual([round1.id, round2.id, turn1.id, turn2.id]);
    thread.clearLive();
    expect(thread.liveIds.value).toEqual([]);
  });

  it('передача хода не теряет предыдущий ход', () => {
    const thread = useCombatChatThread(91002);
    thread.clearLive();
    thread.beginRound();
    const turn1 = thread.beginTurn();
    const turn2 = thread.beginTurn();
    const turn3 = thread.beginTurn();

    expect(thread.liveIds.value).toContain(turn2.id);
    expect(thread.liveIds.value).toContain(turn3.id);
    expect(thread.liveIds.value).not.toContain(turn1.id);
    thread.clearLive();
  });

  it('оставляет последний завершённый удар раскрытым', () => {
    const thread = useCombatChatThread(91006);
    thread.clearLive();
    const attack = thread.beginAttack();
    thread.endAttack();

    expect(thread.liveIds.value).toContain(attack.id);
    expect(thread.stamp()?.id).not.toBe(attack.id);
    thread.clearLive();
  });

  it('recoverFromMessages поднимает два последних хода и раунда', () => {
    const thread = useCombatChatThread(91003);
    thread.clearLive();
    const messages = [
      { thread: { id: 'r1', kind: COMBAT_CHAT_ROUND } },
      { thread: { id: 't1', parentId: 'r1', kind: COMBAT_CHAT_TURN } },
      { thread: { id: 'r2', kind: COMBAT_CHAT_ROUND } },
      { thread: { id: 't2', parentId: 'r2', kind: COMBAT_CHAT_TURN } },
    ] as ChatMessage[];
    thread.recoverFromMessages(messages);
    expect(thread.liveIds.value).toEqual(['r1', 'r2', 't1', 't2']);
    thread.clearLive();
  });

  it('смена gameId читает штампы другой игры', () => {
    const first = useCombatChatThread(91004);
    first.clearLive();
    const roundA = first.beginRound();

    const gameId = ref(91004);
    const shared = useCombatChatThread(gameId);
    expect(shared.stamp()?.id).toBe(roundA.id);

    gameId.value = 91005;
    const second = useCombatChatThread(91005);
    second.clearLive();
    const roundB = second.beginRound();
    expect(shared.stamp()?.id).toBe(roundB.id);
    first.clearLive();
    second.clearLive();
  });
});
