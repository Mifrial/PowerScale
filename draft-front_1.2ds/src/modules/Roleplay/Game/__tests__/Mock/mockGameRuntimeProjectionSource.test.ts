import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { registerAuthApi } from '@/modules/Core/Auth/init';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { mockAuthApi } from '@/modules/Core/Auth/Mock/mockAuthApi';
import { clearMockGameState } from '@/modules/Roleplay/Game/Mock/mockGameState';
import { MockGameRuntimeProjectionSource } from '@/modules/Roleplay/Game/Mock/MockGameRuntimeProjectionSource';
import { fetchNpc, fetchNpcSummaries } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { fetchCharacterModerationProjections } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';

describe('MockGameRuntimeProjectionSource', () => {
  beforeEach(async () => {
    registerAuthApi(mockAuthApi);
    await mockLogin('player@test.com', 'test');
  });

  afterEach(() => {
    clearMockGameState();

    return mockLogout();
  });

  it('returns a visibility-safe batch without full sheets for summaries', async () => {
    const source = new MockGameRuntimeProjectionSource();
    const result = await source.getRuntimeEntities(2, {
      entityKeys: ['character:1', 'npc:5'],
      projectionLevel: 'summary',
    });

    expect(result.missingEntityKeys).toEqual([]);
    expect(result.projections).toHaveLength(2);
    expect(result.projections.every((projection) => projection.projectionLevel === 'summary')).toBe(true);
    expect(result.projections.every((projection) => projection.version === null)).toBe(true);
    expect(result.projections.map((projection) => projection.entityKey)).toEqual(['character:1', 'npc:5']);
  });

  it('returns paged NPC summaries without NPC versions', async () => {
    const result = await fetchNpcSummaries({ gameId: 1, limit: 1 });

    expect(result.items).toHaveLength(1);
    expect(result.nextCursor).toBe('1');
    expect(result.items[0]).not.toHaveProperty('version');
  });

  it('keeps full NPC reads on demand', async () => {
    const npc = await fetchNpc(2, 5);

    expect(npc.id).toBe(5);
    expect(npc).toHaveProperty('version');
    await expect(fetchNpc(1, 5)).rejects.toThrow('НПС не найден');
  });

  it('loads selected character and NPC projections in one batch boundary', async () => {
    const source = new MockGameRuntimeProjectionSource();

    const result = await source.getRuntimeEntities(2, {
      entityKeys: ['character:1', 'npc:5'],
      projectionLevel: 'full',
    });

    expect(result.missingEntityKeys).toEqual([]);
    expect(result.projections.map((projection) => projection.entityKey)).toEqual(['character:1', 'npc:5']);
    expect(result.projections.find((projection) => projection.entityKey === 'character:1')?.version).not.toBeNull();
    expect(result.projections.find((projection) => projection.entityKey === 'npc:5')?.version).toBeNull();
  });

  it('returns missing keys separately for a partial batch response', async () => {
    const source = new MockGameRuntimeProjectionSource();

    const result = await source.getRuntimeEntities(2, {
      entityKeys: ['character:1', 'npc:5', 'npc:999'],
      projectionLevel: 'full',
    });

    expect(result.projections.map((projection) => projection.entityKey)).toEqual(['character:1', 'npc:5']);
    expect(result.missingEntityKeys).toEqual(['npc:999']);
  });

  it('does not return a runtime projection for an inaccessible NPC', async () => {
    const source = new MockGameRuntimeProjectionSource();
    const result = await source.getRuntimeEntity(1, 'npc:2', 'summary');

    expect(result).toBeNull();
  });

  it('redacts a partial full Character projection to visible sections', async () => {
    const source = new MockGameRuntimeProjectionSource();
    const result = await source.getRuntimeEntity(1, 'character:3', 'full');

    expect(result?.visibleSections).toEqual(['shortDescription']);
    expect(result?.version?.shortDescription).toBe('Ловкий карманник с сомнительной репутацией.');
    expect(result?.version?.states).toEqual([]);
  });

  it('builds moderation projections from membership baseline and actual', async () => {
    const projections = await fetchCharacterModerationProjections(2, [1]);

    expect(projections).toHaveLength(1);
    expect(projections[0].characterId).toBe(1);
    expect(projections[0].actualCharacterVersion).not.toBeNull();
    expect(projections[0].diff).not.toBeNull();
  });
});
