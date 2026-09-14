import { describe, expect, it } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { MagicPathStudyCostService } from '@/modules/Roleplay/Character/Service/MagicPathStudyCostService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { keywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';

const cost = new MagicPathStudyCostService();
const editor = new CharacterEditorService();
const config: CharacterCreationConfig = { osTotal: 20, orTotal: 20, moneyBudget: 100 };
const arcanistCost = { discount_fraction: 0.5, pair_base_cost: 1 };

function makeBuild(abilities: CharacterBuild['abilities']): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities,
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
  };
}

describe('MagicPathStudyCostService', () => {
  it('скидка Арканиста: 3 − floor(3×0.5) = 2', () => {
    expect(cost.discounted(3, arcanistCost)).toBe(2);
  });

  it('два заклинания за 1 ОР стоят 1 очко, третье — ещё 1', () => {
    const two = makeBuild([
      { ruleCode: 'becoming-arcanist', level: 1 },
      { ruleCode: 'discharge', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
      { ruleCode: 'lightning-strike', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
    ]);
    const three = makeBuild([
      ...two.abilities,
      { ruleCode: 'lightning-generator', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
    ]);
    expect(editor.build(two, ruleCatalog, config, keywords).budgets.or.spent).toBe(3);
    expect(editor.build(three, ruleCatalog, config, keywords).budgets.or.spent).toBe(4);
  });

  it('улучшение заклинания за 1 ОР входит в пару Арканиста', () => {
    const withUpgrade = makeBuild([
      { ruleCode: 'becoming-arcanist', level: 1 },
      { ruleCode: 'lightning-strike', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
      { ruleCode: 'chain-lightning', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
    ]);
    expect(editor.build(withUpgrade, ruleCatalog, config, keywords).budgets.or.spent).toBe(3);
  });

  it('следующий экземпляр 1 ОР бесплатен, если уже взята нечётная пара', () => {
    const one = makeBuild([
      { ruleCode: 'becoming-arcanist', level: 1 },
      { ruleCode: 'discharge', level: 1, domain: 'Арканист', domainCode: 'arcanist' },
    ]);
    const strike = editor
      .build(one, ruleCatalog, config, keywords)
      .abilities.find((a) => a.ruleCode === 'lightning-strike');
    expect(strike?.nextInstanceCost).toBe(0);
    const discharge = editor
      .build(one, ruleCatalog, config, keywords)
      .abilities.find((a) => a.ruleCode === 'discharge');
    expect(discharge?.instances[0]?.paidCost).toBe(1);
  });

  it('бесплатный псионический разряд не открывает второе заклинание Арканиста за 0', () => {
    const mixed = makeBuild([
      { ruleCode: 'becoming-arcanist', level: 1 },
      { ruleCode: 'psionic-awakening', level: 1 },
      { ruleCode: 'discharge', level: 1, domain: 'Псионик', domainCode: 'psionic' },
    ]);
    const model = editor.build(mixed, ruleCatalog, config, keywords);
    const discharge = model.abilities.find((a) => a.ruleCode === 'discharge');
    const strike = model.abilities.find((a) => a.ruleCode === 'lightning-strike');
    expect(discharge?.nextInstanceCost).toBe(1);
    expect(strike?.domainOptions).toEqual([]);
    expect(strike?.nextInstanceCost).toBeUndefined();
  });
});
