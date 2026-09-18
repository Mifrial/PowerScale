import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { dodgeSoakService } from '@/modules/Roleplay/Game/Service/Instance/dodgeSoakService';

function overview(code: string, value: { base: number; size: number }): CharacterOverview {
  return { characteristics: [{ ruleCode: code, value }] } as unknown as CharacterOverview;
}

const rules: Rule[] = [
  { id: null, code: 'dexterity', type: 'characteristic', name: 'Ловкость' } as Rule,
  { id: null, code: 'reaction', type: 'characteristic', name: 'Реакция' } as Rule,
];

describe('DodgeSoakService', () => {
  it('S0 = Ловкость.modify(−3); не на блоке', () => {
    const defender = overview('dexterity', { base: 4, size: 1 });
    expect(
      dodgeSoakService.amount({ reaction: 'dodge', defenderOverview: defender, rules, sr: 1 }),
    ).toBe(4);
    expect(
      dodgeSoakService.amount({ reaction: 'block', defenderOverview: defender, rules, sr: 1 }),
    ).toBe(0);
  });

  it('Направленный: −размер, при РУ ≥ 3 soak 0', () => {
    const defender = overview('dexterity', { base: 4, size: 2 });
    expect(
      dodgeSoakService.amount({
        reaction: 'dodge',
        defenderOverview: defender,
        rules,
        sr: 2,
        cuts: { sizeDelta: -3 },
      }),
    ).toBe(4);
    expect(
      dodgeSoakService.amount({
        reaction: 'dodge',
        defenderOverview: defender,
        rules,
        sr: 3,
        cuts: { sizeDelta: -3, ignoreAtSr: 3 },
      }),
    ).toBe(0);
  });

  it('Стремительный: −Реакция.toNumber()', () => {
    const defender = overview('dexterity', { base: 4, size: 1 });
    expect(
      dodgeSoakService.amount({
        reaction: 'dodge',
        defenderOverview: defender,
        rules,
        sr: 1,
        cuts: { subtract: 8 },
      }),
    ).toBe(0);
  });
});
