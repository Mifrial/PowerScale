import { describe, expect, it } from 'vitest';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { SpellCastEfficiencyService } from '@/modules/Roleplay/Game/Service/SpellCastEfficiencyService';
import { CHECK_SPELL_CAST_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

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
    const next = service.apply(spec(), CHECK_SPELL_CAST_CODE, [{ sourceCode: 'mastery', delta: 1 }]);
    expect(next.efficiency).toBe(4);
    expect(next.efficiencySize).toBe(0);
  });

  it('два бонуса одного источника не складываются', () => {
    const next = service.apply(spec(), CHECK_SPELL_CAST_CODE, [
      { sourceCode: 'mastery', delta: 1 },
      { sourceCode: 'mastery', delta: 1 },
    ]);
    expect(next.efficiency).toBe(4);
    expect(next.efficiencySize).toBe(0);
  });

  it('бонусы разных источников складываются', () => {
    const next = service.apply(spec(), CHECK_SPELL_CAST_CODE, [
      { sourceCode: 'mastery', delta: 1 },
      { sourceCode: 'other', delta: 1 },
    ]);
    expect(next.efficiency).toBe(5);
    expect(next.efficiencySize).toBe(0);
  });

  it('чужая проверка не меняет спецификацию', () => {
    const source = spec();
    expect(service.apply(source, 'check-intellect', [{ sourceCode: 'mastery', delta: 1 }])).toBe(source);
  });

  it('без свойства базовых элементов дельта не создаётся', () => {
    expect(service.deltasForAbilities([{ ruleCode: 'discharge', level: 1 }])).toEqual([]);
    expect(service.deltasForAbilities([{ ruleCode: 'basic-element-properties', level: 1 }])).toEqual([
      { sourceCode: 'mastery', delta: 1 },
    ]);
  });

  it('взаимодействие даёт +2 мастерства', () => {
    expect(service.deltasForAbilities([{ ruleCode: 'magic-structure-interaction', level: 1 }])).toEqual([
      { sourceCode: 'mastery', delta: 2 },
    ]);
  });

  it('взаимодействие и свойства базовых элементов дают лучший бонус источника', () => {
    const deltas = service.deltasForAbilities([
      { ruleCode: 'basic-element-properties', level: 1 },
      { ruleCode: 'magic-structure-interaction', level: 1 },
    ]);
    const next = service.apply(spec(), CHECK_SPELL_CAST_CODE, deltas);

    expect(next.efficiency).toBe(5);
    expect(next.efficiencySize).toBe(0);
  });
});
