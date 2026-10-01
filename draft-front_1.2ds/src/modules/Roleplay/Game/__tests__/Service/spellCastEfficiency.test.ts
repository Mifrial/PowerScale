import { describe, expect, it } from 'vitest';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { SpellCastEfficiencyService } from '@/modules/Roleplay/Game/Service/SpellCastEfficiencyService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
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

function skill(code: string, amount: number, checkCode = CHECK_SPELL_CAST_CODE): Rule {
  return {
    id: 1,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    mechanics: [],
    createdAt: 1,
    spec: {
      type: 'skill',
      zones: {},
      requirements: [],
      grants: [
        {
          level: 1,
          grants: [{ type: 'check_efficiency', amount, check_codes: [checkCode], source_code: 'mastery' }],
        },
      ],
      parent_ability_code: null,
    },
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

  it('грант чужой проверки и нулевой уровень не дают дельту', () => {
    const rules = [skill('element-skill', 1, 'check-intellect')];
    expect(
      service.deltasForAbilities([{ ruleCode: 'element-skill', level: 1 }], rules, CHECK_SPELL_CAST_CODE),
    ).toEqual([]);
    expect(
      service.deltasForAbilities([{ ruleCode: 'element-skill', level: 0 }], rules, 'check-intellect'),
    ).toEqual([]);
  });

  it('грант +1 и +2 одного источника дают эффективность 5', () => {
    const rules = [skill('basic', 1), skill('interaction', 2)];
    const deltas = service.deltasForAbilities(
      [
        { ruleCode: 'basic', level: 1 },
        { ruleCode: 'interaction', level: 1 },
      ],
      rules,
      CHECK_SPELL_CAST_CODE,
    );
    const next = service.apply(spec(), deltas);

    expect(next.efficiency).toBe(5);
    expect(next.efficiencySize).toBe(0);
  });
});
