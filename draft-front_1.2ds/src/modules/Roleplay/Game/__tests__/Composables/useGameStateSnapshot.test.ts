import { describe, expect, it } from 'vitest';
import { useGameStateSnapshot } from '@/modules/Roleplay/Game/Composables/useGameStateSnapshot';

describe('useGameStateSnapshot', () => {
  it('разделяет active predicates и terminal transition predicates', () => {
    const state = useGameStateSnapshot();

    state.applySnapshot({ gameId: 2, session: null, battle: null });
    expect(state.isSessionActive.value).toBe(false);
    expect(state.isBattleActive.value).toBe(false);
    expect(state.isBattleEnded.value).toBe(false);
    expect(state.isSessionStopped.value).toBe(false);

    state.applyLifecycleResult({
      kind: 'transition',
      commandId: 'end-battle-1',
      status: 'ended',
      snapshot: { gameId: 2, session: null, battle: null },
      transition: {
        type: 'battle_ended',
        sessionId: 'session-1',
        battleId: null,
        endedBattleId: 'battle-1',
        cleanup: null,
      },
    });

    expect(state.isBattleEnded.value).toBe(true);
    expect(state.isSessionStopped.value).toBe(false);
  });

  it('не выводит terminal состояние из null snapshot после обычного чтения', () => {
    const state = useGameStateSnapshot();

    state.applyLifecycleResult({
      kind: 'transition',
      commandId: 'stop-session-1',
      status: 'stopped',
      snapshot: { gameId: 2, session: null, battle: null },
      transition: {
        type: 'session_stopped',
        sessionId: null,
        battleId: null,
        endedBattleId: null,
        cleanup: null,
      },
    });
    expect(state.isSessionStopped.value).toBe(true);

    state.applySnapshot({ gameId: 2, session: null, battle: null });
    expect(state.isSessionStopped.value).toBe(false);
  });
});
