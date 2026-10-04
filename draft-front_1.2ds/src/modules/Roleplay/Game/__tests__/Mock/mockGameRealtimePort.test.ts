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

  it('assigns monotonic gameId.cursor event ids and deduplicates sync by cursor', async () => {
    const port = new MockGameRealtimePort();
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

      const result = await port.sync(7, { lastCursor: 0 });

      expect(result.kind).toBe('events');
      if (result.kind !== 'events') return;
      expect(result.events.map((event) => event.eventId)).toEqual(['7.1', '7.2']);
      expect(result.events.map((event) => event.cursor)).toEqual([1, 2]);
    } finally {
      port.dispose();
    }
  });

  it('falls back to a bounded snapshot after the retained event window is stale', async () => {
    const port = new MockGameRealtimePort();
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

      const result = await port.sync(8, { lastCursor: 0, entityKeys: ['npc:2'] });

      expect(result.kind).toBe('snapshot');
      if (result.kind !== 'snapshot') return;
      expect(result.currentCursor).toBe(101);
      expect(result.projections.length).toBeLessThanOrEqual(1);
    } finally {
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
