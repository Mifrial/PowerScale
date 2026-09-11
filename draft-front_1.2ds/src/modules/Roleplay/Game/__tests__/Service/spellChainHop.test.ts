import { describe, expect, it } from 'vitest';
import { spellChainHopService } from '@/modules/Roleplay/Game/Service/Instance/spellChainHopService';
import type { SpellChain } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChain';

const chain: SpellChain = {
  damage_size_per_hop: 1,
  min: { base: 3, size: -1 },
  retarget: 'from_last_hit',
  same_target: 'via_other',
};

describe('SpellChainHopService', () => {
  it('hop только при повреждениях и другой цели', () => {
    expect(
      spellChainHopService.canHop({
        raw: 0,
        nextKey: 'character:3',
        lastKey: 'character:2',
        alreadyHit: ['character:2'],
        sameTarget: 'via_other',
      }),
    ).toBe(false);
    expect(
      spellChainHopService.canHop({
        raw: 6,
        nextKey: 'character:2',
        lastKey: 'character:2',
        alreadyHit: ['character:2'],
        sameTarget: 'via_other',
      }),
    ).toBe(false);
    expect(
      spellChainHopService.canHop({
        raw: 6,
        nextKey: 'character:3',
        lastKey: 'character:2',
        alreadyHit: ['character:2'],
        sameTarget: 'via_other',
      }),
    ).toBe(true);
    expect(
      spellChainHopService.canHop({
        raw: 6,
        nextKey: 'character:2',
        lastKey: 'character:3',
        alreadyHit: ['character:2', 'character:3'],
        sameTarget: 'via_other',
      }),
    ).toBe(true);
  });

  it('уменьшает размер за hop и обрывается ниже min', () => {
    const first = spellChainHopService.nextAmount({ base: 4, size: 0 }, chain, 0, undefined);
    expect(first).toEqual({ base: 4, size: -1 });
    expect(spellChainHopService.nextAmount(first!, chain, 0, undefined)).toBeNull();
    expect(spellChainHopService.nextAmount({ base: 3, size: -1 }, chain, 0, undefined)).toBeNull();
  });
});
