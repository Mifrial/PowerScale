import { afterEach, describe, expect, it, vi } from 'vitest';
import { MockGameRealtimePort } from '@/modules/Roleplay/Game/Mock/MockGameRealtimePortImplementation';
import { characterChangePort } from '@/modules/Roleplay/Character/init';
import {
  clearMockGameState,
  startGameSession,
  configureMockGameState,
} from '@/modules/Roleplay/Game/Mock/mockGameState';

describe('MockGameRealtimePort', () => {
  afterEach(() => {
    clearMockGameState();
  });

  it('assigns monotonic gameId.cursor event ids and delivers them in order', () => {
    const port = new MockGameRealtimePort();
    const delivered: string[] = [];
    const stop = port.subscribe(7, (event) => delivered.push(event.eventId));

    try {
      port.publishEntityChange({
        gameId: 7,
        entityKey: 'character:1',
        actualVersion: 3,
        changedSections: ['states'],
        eventKind: 'character.changed',
        sessionId: 'session-1',
        battleId: 'battle-1',
      });
      port.publishEntityChange({
        gameId: 7,
        entityKey: 'npc:2',
        actualVersion: 4,
        changedSections: ['resources'],
        eventKind: 'npc.changed',
        sessionId: 'session-1',
        battleId: 'battle-1',
      });

      expect(delivered).toEqual(['7.1', '7.2']);
    } finally {
      stop();
      port.dispose();
    }
  });

  it('keeps cursor monotonic after the retained window is exceeded', () => {
    const port = new MockGameRealtimePort();
    let lastCursor = 0;
    const stop = port.subscribe(8, (event) => {
      lastCursor = event.cursor;
    });

    try {
      for (let cursor = 1; cursor <= 101; cursor += 1) {
        port.publishEntityChange({
          gameId: 8,
          entityKey: 'npc:2',
          actualVersion: cursor,
          changedSections: ['states'],
          eventKind: 'npc.changed',
          sessionId: 'session-1',
          battleId: null,
        });
      }

      expect(lastCursor).toBe(101);
    } finally {
      stop();
      port.dispose();
    }
  });

  it('turns an active CharacterChanged fact into a Game-scoped affected entity event', async () => {
    const port = new MockGameRealtimePort();
    const events: string[] = [];
    const stop = port.subscribe(2, (event) => events.push(event.eventId));

    try {
      configureMockGameState();
      const session = await startGameSession({
        commandId: 'realtime-session',
        commandType: 'startSession',
        gameId: 2,
        sessionId: null,
        battleId: null,
        participantEntityKeys: ['character:1'],
        payload: {},
      });
      expect(session.kind).toBe('transition');

      characterChangePort.publish({
        characterId: 1,
        actualVersion: 12,
        changedSections: ['states'],
        actorId: 1,
        mutationKind: 'runtime_effect',
      });

      await vi.waitFor(() => expect(events).toEqual(['2.1']));
    } finally {
      stop();
      port.dispose();
    }
  });
});
