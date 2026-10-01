import { describe, expect, it } from 'vitest';
import { reactive } from 'vue';
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

  it('строит patch из реактивного листа, не падая на structuredClone', () => {
    const before = structuredClone(versions[1]);
    const after = reactive(structuredClone(before));
    after.states = [...after.states, { stateRuleCode: 'burning', dimensionalValue: { base: 3, size: 0 } }];

    const patch = characterPatchService.createPatch(before, after, 'command-2', 1);

    expect(
      patch.operations.some((operation) => operation.kind === 'replaceSection' && operation.section === 'states'),
    ).toBe(true);
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
