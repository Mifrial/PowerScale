import { afterEach, describe, expect, it } from 'vitest';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  clearMockGameState,
  configureMockGameState,
  endGameBattle,
  getGameInitiative,
  getGameStateSnapshot,
  restoreGameStateSnapshot,
  saveGameInitiative,
  serializeGameStateSnapshot,
  startGameBattle,
  startGameSession,
  stopGameStateSession,
} from '@/modules/Roleplay/Game/Mock/mockGameState';
import type { GameLifecycleCommand } from '@/modules/Roleplay/Game/Dto/GameLifecycleCommand';

const sessionGame = gameDetails.find((detail) => detail.game.id === 2)?.game;

function command<T extends GameLifecycleCommand['commandType']>(
  commandType: T,
  values: Partial<Extract<GameLifecycleCommand, { commandType: T }>> = {},
): Extract<GameLifecycleCommand, { commandType: T }> {
  return {
    commandId: `${commandType}-${Math.random()}`,
    commandType,
    gameId: sessionGame?.id ?? 2,
    sessionId: null,
    battleId: null,
    participantEntityKeys: [],
    payload: {},
    ...values,
  } as unknown as Extract<GameLifecycleCommand, { commandType: T }>;
}

describe('mockGameState', () => {
  afterEach(() => {
    clearMockGameState();
  });

  it('creates one session, replays a command and does not replace an active participant set', async () => {
    configureMockGameState();
    expect((await getGameStateSnapshot(sessionGame?.id ?? 2)).gameId).toBe(sessionGame?.id ?? 2);
    const start = command('startSession', { commandId: 'session-1', participantEntityKeys: ['character:1'] });

    const created = await startGameSession(start);
    const replay = await startGameSession(start);
    const alreadyActive = await startGameSession(
      command('startSession', { commandId: 'session-2', participantEntityKeys: ['npc:5'] }),
    );

    expect(created.kind).toBe('transition');
    expect(created.status).toBe('created');
    expect(replay).toEqual(created);
    expect(alreadyActive.kind).toBe('transition');
    expect(alreadyActive.status).toBe('already_active');
    if (alreadyActive.kind === 'transition') {
      expect(alreadyActive.snapshot.session?.participantEntityKeys).toEqual(['character:1']);
    }
  });

  it('resolves current eligible participants inside the session boundary', async () => {
    configureMockGameState({
      resolveParticipants: async () => ['character:1', 'npc:5'],
      validateParticipants: async (_gameId, participantEntityKeys) =>
        participantEntityKeys.join(',') === 'character:1,npc:5',
    });

    const created = await startGameSession(
      command('startSession', {
        commandId: 'session-server-admission',
        participantAdmission: 'currentEligible',
        participantEntityKeys: [],
      }),
    );

    expect(created.kind).toBe('transition');
    if (created.kind === 'transition') {
      expect(created.snapshot.session?.participantEntityKeys).toEqual(['character:1', 'npc:5']);
    }
  });

  it('stores initiative inside the active battle and clears it with that battle', async () => {
    configureMockGameState();
    const createdSession = await startGameSession(command('startSession', { commandId: 'initiative-session' }));
    if (createdSession.kind !== 'transition' || !createdSession.snapshot.session) {
      throw new Error('Session was not created');
    }

    const createdBattle = await startGameBattle(
      command('startBattle', {
        commandId: 'initiative-battle',
        sessionId: createdSession.snapshot.session.sessionId,
        expectedSessionStateVersion: 0,
      }),
    );
    if (createdBattle.kind !== 'transition' || !createdBattle.snapshot.battle) {
      throw new Error('Battle was not created');
    }

    const initiative = {
      gameId: createdSession.snapshot.session.gameId,
      active: true,
      participants: [{ id: 'character:1', name: 'Торвин', kind: 'character' as const, entityId: 1 }],
      activeIndex: 0,
      round: 1,
      updatedAt: '',
    };
    await saveGameInitiative(initiative.gameId, initiative);
    expect((await getGameInitiative(initiative.gameId)).participants).toHaveLength(1);

    await endGameBattle(
      command('endBattle', {
        commandId: 'initiative-end-battle',
        sessionId: createdSession.snapshot.session.sessionId,
        battleId: createdBattle.snapshot.battle.battleId,
        expectedSessionStateVersion: 1,
        expectedBattleStateVersion: 1,
      }),
    );
    expect((await getGameInitiative(initiative.gameId)).participants).toHaveLength(0);
  });

  it('separates independent battles and rejects stale battle transitions', async () => {
    configureMockGameState();
    const createdSession = await startGameSession(command('startSession', { commandId: 'session-1' }));
    if (createdSession.kind !== 'transition' || !createdSession.snapshot.session)
      throw new Error('Session was not created');

    const sessionId = createdSession.snapshot.session.sessionId;
    const firstBattle = await startGameBattle(
      command('startBattle', {
        commandId: 'battle-1',
        sessionId,
        expectedSessionStateVersion: 0,
      }),
    );
    if (firstBattle.kind !== 'transition' || !firstBattle.snapshot.battle) throw new Error('Battle was not created');

    const staleEnd = await endGameBattle(
      command('endBattle', {
        commandId: 'end-stale',
        sessionId,
        battleId: firstBattle.snapshot.battle.battleId,
        expectedSessionStateVersion: 0,
        expectedBattleStateVersion: 0,
      }),
    );
    expect(staleEnd.kind).toBe('conflict');

    const ended = await endGameBattle(
      command('endBattle', {
        commandId: 'end-1',
        sessionId,
        battleId: firstBattle.snapshot.battle.battleId,
        expectedSessionStateVersion: 1,
        expectedBattleStateVersion: 0,
      }),
    );
    expect(ended.kind).toBe('transition');
    if (ended.kind !== 'transition') return;
    expect(ended.transition.endedBattleId).toBe(firstBattle.snapshot.battle.battleId);
    expect(ended.snapshot.battle).toBeNull();
    expect(
      await endGameBattle(
        command('endBattle', {
          commandId: 'end-1',
          sessionId,
          battleId: firstBattle.snapshot.battle.battleId,
          expectedSessionStateVersion: 1,
          expectedBattleStateVersion: 0,
        }),
      ),
    ).toEqual(ended);

    const secondBattle = await startGameBattle(
      command('startBattle', {
        commandId: 'battle-2',
        sessionId,
        expectedSessionStateVersion: 2,
      }),
    );
    expect(secondBattle.kind).toBe('transition');
    if (secondBattle.kind === 'transition') {
      expect(secondBattle.snapshot.battle?.battleId).not.toBe(firstBattle.snapshot.battle.battleId);
    }
  });

  it('does not re-run session admission before starting a new battle', async () => {
    let participantsEligible = true;
    configureMockGameState({
      validateParticipants: async () => participantsEligible,
    });

    const createdSession = await startGameSession(command('startSession', { commandId: 'session-1' }));
    if (createdSession.kind !== 'transition' || !createdSession.snapshot.session) {
      throw new Error('Session was not created');
    }

    participantsEligible = false;
    const battle = await startGameBattle(
      command('startBattle', {
        commandId: 'battle-1',
        sessionId: createdSession.snapshot.session.sessionId,
        expectedSessionStateVersion: 0,
      }),
    );

    expect(battle.kind).toBe('transition');
    expect(participantsEligible).toBe(false);
  });

  it('restores the same IDs and versions through fixture serialization', async () => {
    configureMockGameState();
    const createdSession = await startGameSession(command('startSession', { commandId: 'session-1' }));
    if (createdSession.kind !== 'transition' || !createdSession.snapshot.session)
      throw new Error('Session was not created');
    await startGameBattle(
      command('startBattle', {
        commandId: 'battle-1',
        sessionId: createdSession.snapshot.session.sessionId,
        expectedSessionStateVersion: 0,
      }),
    );
    const before = await getGameStateSnapshot(createdSession.snapshot.session.gameId);

    const serialized = serializeGameStateSnapshot(createdSession.snapshot.session.gameId);
    clearMockGameState();
    restoreGameStateSnapshot(JSON.parse(serialized));
    const after = await getGameStateSnapshot(createdSession.snapshot.session.gameId);

    expect(after).toEqual(before);
    expect(
      await startGameBattle(
        command('startBattle', {
          commandId: 'battle-1',
          sessionId: createdSession.snapshot.session.sessionId,
          expectedSessionStateVersion: 0,
        }),
      ),
    ).toEqual(
      await startGameBattle(
        command('startBattle', {
          commandId: 'battle-1',
          sessionId: createdSession.snapshot.session.sessionId,
          expectedSessionStateVersion: 0,
        }),
      ),
    );
  });

  it('cleans session state and rejects a command from the closed session', async () => {
    configureMockGameState();
    const createdSession = await startGameSession(command('startSession', { commandId: 'session-1' }));
    if (createdSession.kind !== 'transition' || !createdSession.snapshot.session)
      throw new Error('Session was not created');

    const stopped = await stopGameStateSession(
      command('stopSession', {
        commandId: 'stop-1',
        sessionId: createdSession.snapshot.session.sessionId,
        expectedSessionStateVersion: 0,
      }),
    );
    expect(stopped.kind).toBe('transition');
    expect((await getGameStateSnapshot(createdSession.snapshot.session.gameId)).session).toBeNull();
    expect(
      await stopGameStateSession(
        command('stopSession', {
          commandId: 'stop-1',
          sessionId: createdSession.snapshot.session.sessionId,
          expectedSessionStateVersion: 0,
        }),
      ),
    ).toEqual(stopped);

    const oldStart = await startGameSession(
      command('startSession', { commandId: 'session-1', participantEntityKeys: ['character:1'] }),
    );
    expect(oldStart.kind).toBe('conflict');
    if (oldStart.kind === 'conflict') expect(oldStart.conflict.code).toBe('closed_session');
  });
});
