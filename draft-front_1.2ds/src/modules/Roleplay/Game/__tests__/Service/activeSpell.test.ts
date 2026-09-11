import { describe, expect, it } from 'vitest';
import { activeSpellService } from '@/modules/Roleplay/Game/Service/Instance/activeSpellService';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const spell: ActiveSpell = {
  id: '1',
  gameId: 2,
  casterKey: 'character:1',
  spellCode: 'lightning-generator',
  sourceKey: 'inventory:9',
  pathCode: 'arcanist',
  durationType: 'sustained',
  usedPower: { base: 4, size: 0 },
  sustainPower: { base: 4, size: 0 },
  parameterValues: { x: { base: 4, size: 0 } },
  appliedUpgradeCodes: [],
  startedRound: 1,
  startedParticipantId: 'character:1',
  stability: { base: 3, size: 0 },
};

describe('ActiveSpellService', () => {
  it('occupy только у этого кастера', () => {
    expect(activeSpellService.isSourceOccupied([spell], 'character:1', 'inventory:9')).toBe(true);
    expect(activeSpellService.isSourceOccupied([spell], 'character:2', 'inventory:9')).toBe(false);
  });

  it('не спрашивает поддержание в ход сотворения', () => {
    expect(activeSpellService.shouldPromptSustain(spell, 1, 'character:1')).toBe(false);
    expect(activeSpellService.shouldPromptSustain(spell, 2, 'character:1')).toBe(true);
    expect(activeSpellService.shouldPromptSustain(spell, 1, 'npc:2')).toBe(false);
  });

  it('inventory без экипировки недоступен', () => {
    const version = {
      inventory: [{ id: 9, ruleCode: 'magic-core', quantity: 1, equipped: false }],
      abilities: [],
    } as unknown as CharacterVersion;
    expect(activeSpellService.isSourceAvailable('inventory:9', version)).toBe(false);
    expect(
      activeSpellService.isSourceAvailable('inventory:9', {
        ...version,
        inventory: [{ id: 9, ruleCode: 'magic-core', quantity: 1, equipped: true }],
      }),
    ).toBe(true);
    expect(
      activeSpellService.isSourceAvailable('grant:becoming-arcanist', {
        inventory: [],
        abilities: [{ ruleCode: 'becoming-arcanist', level: 1 }],
      } as unknown as CharacterVersion),
    ).toBe(true);
  });

  it('стабильность от превышения мощи; drop на равенстве toNumber', () => {
    expect(activeSpellService.stabilityOf({ base: 3, size: 0 }, { base: 3, size: 0 })).toEqual({ base: 3, size: 0 });
    expect(activeSpellService.stabilityOf({ base: 4, size: 0 }, { base: 3, size: 0 })).toEqual({ base: 4, size: 0 });
    expect(activeSpellService.shouldDropFromDisruption(spell, 3)).toBe(true);
    expect(activeSpellService.shouldDropFromDisruption(spell, 2)).toBe(false);
  });

  it('пересчитывает стабильность при смене мощи поддержания', () => {
    const next = activeSpellService.withSustainPower(spell, { base: 5, size: 0 }, { base: 3, size: 0 });
    expect(next.sustainPower).toEqual({ base: 5, size: 0 });
    expect(next.stability).toEqual({ base: 5, size: 0 });
  });
});
