import { describe, expect, it } from 'vitest';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { SpellCastEfficiencyService } from '@/modules/Roleplay/Game/Service/SpellCastEfficiencyService';

const service = new SpellCastEfficiencyService();

function spec(efficiency = 3): DiceRollSpec {
  return {
    diceCount: 1,
    dieSize: 0,
    dieFaces: 6,
    efficiency,
    efficiencySize: 0,
    advantages: [],
  };
}

describe('SpellCastEfficiencyService', () => {
  it('база 3 и один бонус мастерства дают эффективность 4', () => {
    const next = service.apply(spec(), [{ sourceCode: 'mastery', delta: 1 }]);
    expect(next.efficiency).toBe(4);
    expect(next.efficiencySize).toBe(0);
  });

  it('два бонуса одного источника не складываются', () => {
    const next = service.apply(spec(), [
      { sourceCode: 'mastery', delta: 1 },
      { sourceCode: 'mastery', delta: 1 },
    ]);
    expect(next.efficiency).toBe(4);
    expect(next.efficiencySize).toBe(0);
  });

  it('бонусы разных источников складываются', () => {
    const next = service.apply(spec(), [
      { sourceCode: 'mastery', delta: 1 },
      { sourceCode: 'other', delta: 1 },
    ]);
    expect(next.efficiency).toBe(5);
    expect(next.efficiencySize).toBe(0);
  });

  it('пустой список дельт не меняет спецификацию', () => {
    const source = spec();
    expect(service.apply(source, [])).toBe(source);
  });
});
