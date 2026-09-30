import { describe, expect, it } from 'vitest';
import type { CharacterChanged } from '@/modules/Roleplay/Character/Dto/CharacterChanged';
import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';

describe('R2-FE runtime contracts', () => {
  it('models a post-commit CharacterChanged fact without Game delivery fields', () => {
    const fact: CharacterChanged = {
      characterId: 11,
      actualVersion: 4,
      changedSections: ['states'],
      actorId: 7,
      mutationKind: 'runtime_effect',
    };

    expect(fact).not.toHaveProperty('gameId');
    expect(fact).not.toHaveProperty('chatMessage');
  });

  it('allows one authoritative Game result to affect characters and NPCs', () => {
    const result: GameAuthoritativeCommandResult = {
      commandId: 'command-1',
      status: 'applied',
      battleId: 'battle-1',
      process: {
        processId: 'process-1',
        offerId: 3,
        status: 'applied',
        processStateVersion: 2,
      },
      effects: [],
      affectedEntities: [
        { kind: 'character', id: 11, actualVersion: 4, changedSections: ['states'] },
        { kind: 'npc', id: 21, actualVersion: 9, changedSections: ['resources'] },
      ],
      conflict: null,
    };

    expect(result.affectedEntities.map((entity) => entity.kind)).toEqual(['character', 'npc']);
  });

  it('keeps runtime projection ownership explicit for NPCs', () => {
    const projection: GameRuntimeEntityProjection = {
      kind: 'npc',
      id: 21,
      actualVersion: 9,
      actualSpaceCode: null,
      actualRulesRevision: null,
      entityKey: 'npc:21',
      source: 'npcActual',
      projectionLevel: 'summary',
      summary: { name: 'Guard', shortDescription: 'A city guard' },
      version: null,
      visibleSections: ['shortDescription'],
    };

    expect(projection.kind).toBe('npc');
    expect(projection.version).toBeNull();
  });
});
