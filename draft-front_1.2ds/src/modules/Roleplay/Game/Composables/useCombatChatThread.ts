import { computed, reactive, toValue, type MaybeRefOrGetter } from 'vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { ChatThreadRef } from '@/modules/Messages/Chat/Dto/ChatThreadRef';
import {
  COMBAT_CHAT_ATTACK,
  COMBAT_CHAT_BATTLE,
  COMBAT_CHAT_INITIATIVE,
  COMBAT_CHAT_ROUND,
  COMBAT_CHAT_SESSION,
  COMBAT_CHAT_TURN,
} from '@/modules/Roleplay/Game/Constant/Combat/COMBAT_CHAT_FOLD_KINDS';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

interface CombatChatThreadState {
  sessionOpen: boolean;
  liveBattleId: number | null;
  sessionId: string | null;
  roundId: string | null;
  previousRoundId: string | null;
  turnId: string | null;
  previousTurnId: string | null;
  attackId: string | null;
  lastAttackId: string | null;
  initiativeId: string | null;
  lastInitiativeId: string | null;
}

const byGame = new Map<number, CombatChatThreadState>();

function stateOf(gameId: number): CombatChatThreadState {
  let state = byGame.get(gameId);
  if (!state) {
    state = reactive({
      sessionOpen: false,
      liveBattleId: null,
      sessionId: null,
      roundId: null,
      previousRoundId: null,
      turnId: null,
      previousTurnId: null,
      attackId: null,
      lastAttackId: null,
      initiativeId: null,
      lastInitiativeId: null,
    });
    byGame.set(gameId, state);
  }

  return state;
}

function newId(): string {
  return createRandomId();
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
    current.initiativeId = null;
    current.lastInitiativeId = null;

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

  function beginInitiative(): ChatThreadRef {
    const current = state();
    if (!current.roundId) beginRound();
    current.initiativeId = newId();
    current.lastInitiativeId = null;

    return {
      id: current.initiativeId,
      parentId: current.roundId ?? undefined,
      kind: COMBAT_CHAT_INITIATIVE,
    };
  }

  function endAttack(): void {
    const current = state();
    current.lastAttackId = current.attackId;
    current.attackId = null;
  }

  function endInitiative(): void {
    const current = state();
    current.lastInitiativeId = current.initiativeId;
    current.initiativeId = null;
  }

  function clearLive(): void {
    const current = state();
    current.roundId = null;
    current.previousRoundId = null;
    current.turnId = null;
    current.previousTurnId = null;
    current.attackId = null;
    current.lastAttackId = null;
    current.initiativeId = null;
    current.lastInitiativeId = null;
  }

  function stamp(): ChatThreadRef | undefined {
    const current = state();
    if (!current.sessionOpen) return stampLegacy();
    if (current.liveBattleId === null) {
      return current.sessionId ? { id: current.sessionId, kind: COMBAT_CHAT_SESSION } : undefined;
    }
    const prefix = `b${current.liveBattleId}-`;
    if (current.attackId?.startsWith(prefix) || current.attackId?.startsWith(`b${current.liveBattleId}`)) {
      return { id: current.attackId, parentId: current.turnId ?? undefined, kind: COMBAT_CHAT_ATTACK };
    }
    if (current.initiativeId?.startsWith(prefix)) {
      return {
        id: current.initiativeId,
        parentId: current.roundId ?? undefined,
        kind: COMBAT_CHAT_INITIATIVE,
      };
    }
    if (current.turnId?.startsWith(prefix)) {
      return { id: current.turnId, parentId: current.roundId ?? undefined, kind: COMBAT_CHAT_TURN };
    }
    if (current.roundId?.startsWith(prefix)) {
      return { id: current.roundId, parentId: `b${current.liveBattleId}`, kind: COMBAT_CHAT_ROUND };
    }

    return {
      id: `b${current.liveBattleId}`,
      ...(current.sessionId ? { parentId: current.sessionId } : {}),
      kind: COMBAT_CHAT_BATTLE,
    };
  }

  function stampLegacy(): ChatThreadRef | undefined {
    const current = state();
    if (current.attackId) {
      return { id: current.attackId, parentId: current.turnId ?? undefined, kind: COMBAT_CHAT_ATTACK };
    }
    if (current.initiativeId) {
      return {
        id: current.initiativeId,
        parentId: current.roundId ?? undefined,
        kind: COMBAT_CHAT_INITIATIVE,
      };
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

  function latestSessionId(messages: ChatMessage[]): string | null {
    for (let i = messages.length - 1; i >= 0; i--) {
      const thread = messages[i]?.thread;
      if (thread?.kind === COMBAT_CHAT_SESSION) return thread.id;
    }

    return null;
  }

  function adoptLive(messages: ChatMessage[], sessionRunning: boolean, battleId: number | null): void {
    const current = state();
    current.sessionOpen = sessionRunning;
    current.liveBattleId = sessionRunning ? battleId : null;
    current.sessionId = sessionRunning ? latestSessionId(messages) : null;
    if (!sessionRunning) {
      clearLive();
      current.sessionOpen = false;
      current.liveBattleId = null;
      current.sessionId = null;

      return;
    }
    if (battleId === null) {
      current.turnId = null;
      current.previousTurnId = null;
      current.roundId = null;
      current.previousRoundId = null;
      current.attackId = null;
      current.initiativeId = null;
      current.lastAttackId = null;
      current.lastInitiativeId = null;

      return;
    }
    const prefix = `b${battleId}-`;
    recoverFromMessages(
      messages.filter((message) => {
        const id = message.thread?.id ?? '';

        return id === `b${battleId}` || id.startsWith(prefix);
      }),
    );
  }

  function closeBattle(): void {
    const current = state();
    current.liveBattleId = null;
    current.turnId = null;
    current.previousTurnId = null;
    current.roundId = null;
    current.previousRoundId = null;
    current.attackId = null;
    current.initiativeId = null;
    current.lastAttackId = null;
    current.lastInitiativeId = null;
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
      current.initiativeId,
      current.lastInitiativeId,
    ].filter((id): id is string => id != null);
  });

  return {
    beginRound,
    beginTurn,
    beginAttack,
    beginInitiative,
    endAttack,
    endInitiative,
    clearLive,
    stamp,
    recoverFromMessages,
    adoptLive,
    closeBattle,
    liveIds,
  };
}
