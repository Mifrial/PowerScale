import { computed, reactive } from 'vue';
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
    });
    byGame.set(gameId, state);
  }

  return state;
}

function newId(): string {
  return crypto.randomUUID();
}

export function useCombatChatThread(gameId: number) {
  const state = stateOf(gameId);

  function beginRound(): ChatThreadRef {
    if (state.roundId) state.previousRoundId = state.roundId;
    if (state.turnId) state.previousTurnId = state.turnId;
    state.roundId = newId();
    state.turnId = null;
    state.attackId = null;

    return { id: state.roundId, kind: COMBAT_CHAT_ROUND };
  }

  function beginTurn(): ChatThreadRef {
    if (!state.roundId) beginRound();
    if (state.turnId) state.previousTurnId = state.turnId;
    state.turnId = newId();
    state.attackId = null;

    return { id: state.turnId, parentId: state.roundId ?? undefined, kind: COMBAT_CHAT_TURN };
  }

  function beginAttack(): ChatThreadRef {
    state.attackId = newId();

    return {
      id: state.attackId,
      parentId: state.turnId ?? state.roundId ?? undefined,
      kind: COMBAT_CHAT_ATTACK,
    };
  }

  function endAttack(): void {
    state.attackId = null;
  }

  function clearLive(): void {
    state.roundId = null;
    state.previousRoundId = null;
    state.turnId = null;
    state.previousTurnId = null;
    state.attackId = null;
  }

  function stamp(): ChatThreadRef | undefined {
    if (state.attackId) {
      return { id: state.attackId, parentId: state.turnId ?? undefined, kind: COMBAT_CHAT_ATTACK };
    }
    if (state.turnId) {
      return { id: state.turnId, parentId: state.roundId ?? undefined, kind: COMBAT_CHAT_TURN };
    }
    if (state.roundId) {
      return { id: state.roundId, kind: COMBAT_CHAT_ROUND };
    }

    return undefined;
  }

  function recoverFromMessages(messages: ChatMessage[]): void {
    if (state.roundId || state.turnId) return;
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
    state.turnId = turns[0] ?? null;
    state.previousTurnId = turns[1] ?? null;
    state.roundId = rounds[0] ?? (turns[0] ? (turnParent.get(turns[0]) ?? null) : null);
    const previousRound = rounds[1] ?? (turns[1] ? turnParent.get(turns[1]) : undefined);
    state.previousRoundId = previousRound && previousRound !== state.roundId ? previousRound : null;
  }

  const liveIds = computed(() =>
    [state.previousRoundId, state.roundId, state.previousTurnId, state.turnId, state.attackId].filter(
      (id): id is string => id != null,
    ),
  );

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
