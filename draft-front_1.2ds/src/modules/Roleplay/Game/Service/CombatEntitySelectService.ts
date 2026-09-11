import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CombatEntitySelectEntry } from '@/modules/Roleplay/Game/Dto/CombatEntitySelectEntry';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

/** Собирает список персонажей и НПС: сначала участники инициативы, затем остальные. */
export class CombatEntitySelectService {
  items(input: {
    characters: GameCharacterMembership[];
    npcs: GameNpc[];
    initiativeKeys?: readonly string[];
    exclude?: readonly string[];
    leading?: CombatEntitySelectEntry[];
  }): CombatEntitySelectEntry[] {
    const excluded = new Set(input.exclude ?? []);
    const initiative = input.initiativeKeys ?? [];
    const inScale = new Set(initiative);
    const characters = input.characters
      .filter((membership) => membership.membershipStatus === 'active')
      .map((membership) => ({
        value: `character:${membership.characterId}` as CombatEntityKey,
        title: membership.characterName,
        kind: 'character' as const,
      }))
      .filter((item) => !excluded.has(item.value));
    const npcs = input.npcs
      .filter((npc) => npc.status === 'active')
      .map((npc) => ({
        value: `npc:${npc.id}` as CombatEntityKey,
        title: npc.name,
        kind: 'npc' as const,
      }))
      .filter((item) => !excluded.has(item.value));
    const byKey = new Map([...characters, ...npcs].map((item) => [item.value, item]));
    const initCharacters: CombatEntitySelectEntry[] = [];
    const initNpcs: CombatEntitySelectEntry[] = [];
    for (const key of initiative) {
      const item = byKey.get(key as CombatEntityKey);
      if (!item) continue;
      const row = { title: item.title, value: item.value };
      if (item.kind === 'character') initCharacters.push(row);
      else initNpcs.push(row);
    }
    const restCharacters = characters
      .filter((item) => !inScale.has(item.value))
      .map((item) => ({ title: item.title, value: item.value }));
    const restNpcs = npcs
      .filter((item) => !inScale.has(item.value))
      .map((item) => ({ title: item.title, value: item.value }));
    const inBlock = [...initCharacters, ...initNpcs];
    const restBlock = [...restCharacters, ...restNpcs];
    const rows: CombatEntitySelectEntry[] = [...(input.leading ?? [])];
    rows.push(...inBlock);
    if (inBlock.length > 0 && restBlock.length > 0) {
      rows.push({ type: 'divider', title: '', disabled: true });
    }
    rows.push(...restBlock);

    return rows;
  }
}
