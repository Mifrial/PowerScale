import { afterEach, describe, expect, it } from 'vitest';
import type { GameParticipantCandidate } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidate';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  clearMockGameState,
  configureMockGameState,
  startGameSession,
} from '@/modules/Roleplay/Game/Mock/mockGameState';
import { MockGameParticipantCandidateSource } from '@/modules/Roleplay/Game/Mock/MockGameParticipantCandidateSource';

describe('MockGameParticipantCandidateSource', () => {
  afterEach(() => {
    clearMockGameState();
  });

  it('searches a bounded candidate page without exposing full sheets', async () => {
    const source = new MockGameParticipantCandidateSource();

    const result = await source.search({ gameId: 2, query: 'Проф', limit: 1 });

    expect(result.items).toHaveLength(1);
    expect(result.items[0]).toMatchObject({
      entityKey: 'npc:5',
      kind: 'npc',
      name: 'Профессор Шторм',
      availability: 'admissible',
    });
    expect(result.items[0]).not.toHaveProperty('version');
  });

  it('uses the admitted session participant keys after session bootstrap', async () => {
    configureMockGameState({
      getGame: (gameId) => gameDetails.find((detail) => detail.game.id === gameId)?.game ?? null,
    });
    const session = await startGameSession({
      commandId: 'candidate-session',
      commandType: 'startSession',
      gameId: 2,
      sessionId: null,
      battleId: null,
      participantEntityKeys: ['character:1', 'npc:5'],
      payload: {},
    });
    expect(session.kind).toBe('transition');

    const result = await new MockGameParticipantCandidateSource().search({ gameId: 2, limit: 50 });

    expect(result.items.map((candidate) => candidate.entityKey).sort()).toEqual(['character:1', 'npc:5']);
  });

  it('supports cursor pagination for the bounded candidate search', async () => {
    const source = new MockGameParticipantCandidateSource();

    const firstPage = await source.search({ gameId: 2, limit: 1 });
    const secondPage = await source.search({
      gameId: 2,
      limit: 1,
      cursor: firstPage.nextCursor ?? undefined,
    });

    expect(firstPage.nextCursor).toMatch(/^\d+:\d+$/);
    expect(secondPage.items).toHaveLength(1);
    expect(secondPage.items[0].entityKey).not.toBe(firstPage.items[0].entityKey);
    expect(secondPage.nextCursor).toBeNull();
  });

  it('keeps characters before NPCs and does not repeat candidates across pages', async () => {
    const source = new MockGameParticipantCandidateSource();
    const candidates: GameParticipantCandidate[] = [];
    let cursor: string | undefined;

    do {
      const page = await source.search({ gameId: 1, limit: 2, cursor });
      candidates.push(...page.items);
      cursor = page.nextCursor ?? undefined;
    } while (cursor);

    expect(candidates.map((candidate) => candidate.kind)).toEqual(['character', 'npc', 'npc', 'npc']);
    expect(new Set(candidates.map((candidate) => candidate.entityKey)).size).toBe(candidates.length);
  });
});
