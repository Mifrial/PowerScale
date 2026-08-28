import { computed, reactive, toValue, type MaybeRefOrGetter } from 'vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';
import {
  COMBAT_CHAT_ATTACK,
  COMBAT_CHAT_ROUND,
  COMBAT_CHAT_TURN,
} from '@/modules/Roleplay/Game/Constant/Combat/COMBAT_CHAT_FOLD_KINDS';

interface CombatChatThreadState {
  roundId: string | null;
  previousRoundId: string | null;
  turnId: string | null;
  previousTurnId: string | null;
  attackId: string | null;
  lastAttackId: string | null;
}

const byGame = new Map<number, CombatChatThreadState>();

function stateOf(gameId: number): CombatChatThreadState {
  let state = byGame.get(gameId);
  if (!state) {
    state = reactive({
      roundId: null,
      previousRoundId: null,
      turnId: null,
      previousTurnId: null,
      attackId: null,
      lastAttackId: null,
    });
    byGame.set(gameId, state);
  }

  return state;
}

function newId(): string {
  return crypto.randomUUID();
}

/** `gameId` — число, computed или getter: штампы читаются по текущему id, не по снимку setup. */
export function useCombatChatThread(gameId: MaybeRefOrGetter<number>) {
  const currentId = computed(() => toValue(gameId));

  function state(): CombatChatThreadState {
    return stateOf(currentId.value);
  }

  function beginRound(): ChatThreadRef {
    const current = state();
    if (current.roundId) current.previousRoundId = current.roundId;
    if (current.turnId) current.previousTurnId = current.turnId;
    current.roundId = newId();
    current.turnId = null;
    current.attackId = null;
    current.lastAttackId = null;

    return { id: current.roundId, kind: COMBAT_CHAT_ROUND };
  }

  function beginTurn(): ChatThreadRef {
    const current = state();
    if (!current.roundId) beginRound();
    if (current.turnId) current.previousTurnId = current.turnId;
    current.turnId = newId();
    current.attackId = null;
    current.lastAttackId = null;

    return { id: current.turnId, parentId: current.roundId ?? undefined, kind: COMBAT_CHAT_TURN };
  }

  function beginAttack(): ChatThreadRef {
    const current = state();
    current.attackId = newId();
    current.lastAttackId = null;

    return {
      id: current.attackId,
      parentId: current.turnId ?? current.roundId ?? undefined,
      kind: COMBAT_CHAT_ATTACK,
    };
  }

  function endAttack(): void {
    const current = state();
    current.lastAttackId = current.attackId;
    current.attackId = null;
  }

  function clearLive(): void {
    const current = state();
    current.roundId = null;
    current.previousRoundId = null;
    current.turnId = null;
    current.previousTurnId = null;
    current.attackId = null;
    current.lastAttackId = null;
  }

  function stamp(): ChatThreadRef | undefined {
    const current = state();
    if (current.attackId) {
      return { id: current.attackId, parentId: current.turnId ?? undefined, kind: COMBAT_CHAT_ATTACK };
    }
    if (current.turnId) {
      return { id: current.turnId, parentId: current.roundId ?? undefined, kind: COMBAT_CHAT_TURN };
    }
    if (current.roundId) {
      return { id: current.roundId, kind: COMBAT_CHAT_ROUND };
    }

    return undefined;
  }

  function recoverFromMessages(messages: ChatMessage[]): void {
    const current = state();
    if (current.roundId || current.turnId) return;
    const turns: string[] = [];
    const rounds: string[] = [];
    const turnParent = new Map<string, string>();
    for (let i = messages.length - 1; i >= 0; i--) {
      const thread = messages[i]?.thread;
      if (!thread) continue;
      if (thread.kind === COMBAT_CHAT_TURN && !turns.includes(thread.id)) {
        turns.push(thread.id);
        if (thread.parentId) turnParent.set(thread.id, thread.parentId);
      }
      if (thread.kind === COMBAT_CHAT_ROUND && !rounds.includes(thread.id)) rounds.push(thread.id);
    }
    current.turnId = turns[0] ?? null;
    current.previousTurnId = turns[1] ?? null;
    current.roundId = rounds[0] ?? (turns[0] ? (turnParent.get(turns[0]) ?? null) : null);
    const previousRound = rounds[1] ?? (turns[1] ? turnParent.get(turns[1]) : undefined);
    current.previousRoundId = previousRound && previousRound !== current.roundId ? previousRound : null;
  }

  const liveIds = computed(() => {
    const current = state();

    return [
      current.previousRoundId,
      current.roundId,
      current.previousTurnId,
      current.turnId,
      current.attackId,
      current.lastAttackId,
    ].filter((id): id is string => id != null);
  });

  return {
    beginRound,
    beginTurn,
    beginAttack,
    endAttack,
    clearLive,
    stamp,
    recoverFromMessages,
    liveIds,
  };
}
