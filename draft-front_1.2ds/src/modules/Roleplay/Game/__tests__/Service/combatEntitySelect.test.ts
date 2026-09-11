import { describe, expect, it } from 'vitest';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { combatEntitySelectService } from '@/modules/Roleplay/Game/Service/Instance/combatEntitySelectService';

function membership(id: number, name: string): GameCharacterMembership {
  return {
    gameId: 1,
    characterId: id,
    characterName: name,
    membershipStatus: 'active',
  } as unknown as GameCharacterMembership;
}

function npc(id: number, name: string): GameNpc {
  return { id, name, status: 'active' } as unknown as GameNpc;
}

describe('CombatEntitySelectService', () => {
  it('ставит шкалу выше остальных и разделяет блоки', () => {
    const items = combatEntitySelectService.items({
      characters: [membership(1, 'А'), membership(2, 'Б')],
      npcs: [npc(3, 'В'), npc(4, 'Г')],
      initiativeKeys: ['character:2', 'npc:4'],
    });
    expect(items.map((item) => item.value ?? item.type)).toEqual([
      'character:2',
      'npc:4',
      'divider',
      'character:1',
      'npc:3',
    ]);
  });
});
