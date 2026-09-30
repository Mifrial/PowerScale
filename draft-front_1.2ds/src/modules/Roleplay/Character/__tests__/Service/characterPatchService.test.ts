import { describe, expect, it } from 'vitest';
import { characterPatchService } from '@/modules/Roleplay/Character/Service/Instance/characterPatchService';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

describe('CharacterPatchService', () => {
  it('строит typed patch и воспроизводит изменённый candidate actual', () => {
    const before = structuredClone(versions[1]);
    const after: CharacterVersion = {
      ...structuredClone(before),
      money: before.money + 10,
      points: { ...before.points, osSpent: before.points.osSpent + 1 },
      states: [...before.states, { stateRuleCode: 'stunned', value: 1 }],
      inventory: before.inventory.map((item, index) => (index === 0 ? { ...item, quantity: 2 } : item)),
    };

    const patch = characterPatchService.createPatch(before, after, 'command-1', 4);
    const applied = characterPatchService.applyPatch(before, patch.operations);

    expect(patch.commandId).toBe('command-1');
    expect(patch.expectedActualVersion).toBe(4);
    expect(applied).toEqual(after);
  });

  it('отклоняет runtime operation для отсутствующего ресурса', () => {
    expect(() =>
      characterPatchService.applyPatch(versions[1], [
        {
          kind: 'setResourceCurrent',
          ruleCode: 'missing-resource',
          current: { base: 1, size: 0 },
        },
      ]),
    ).toThrow('Resource missing-resource not found');
  });
});
