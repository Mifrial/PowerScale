import { describe, expect, it } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterVersionIntegrityService } from '@/modules/Roleplay/Character/Service/Instance/characterVersionIntegrityService';

const version: CharacterVersion = {
  name: 'Тест',
  shortDescription: null,
  fullDescription: null,
  spaceCode: 'actual',
  rulesRevision: 12,
  raceRuleCode: 'race',
  characteristics: [{ ruleCode: 'characteristic', base: { base: 3, size: 0 }, modifiers: [] }],
  resources: [],
  abilities: [{ ruleCode: 'ability', level: 1 }],
  points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
  money: 0,
  ageYears: null,
  inventory: [{ id: 1, ruleCode: 'item', quantity: 1, equipped: false, modifierRuleCodes: ['modifier'] }],
  states: [{ stateRuleCode: 'state', poison: { poisonRuleCode: 'poison' } }],
  senses: [{ ruleCode: 'sense', value: 1, modifiers: [], status: 'precise', radius: { base: 30, size: 0 } }],
};

describe('CharacterVersionIntegrityService', () => {
  it('finds every rule reference absent from the exact revision', () => {
    const rules = ['race', 'characteristic', 'ability', 'item', 'state', 'sense'].map<Rule>((code) => ({
      id: null,
      code,
      name: code,
      type: 'characteristic',
      description: '',
      spaceId: 2,
      createdAt: '',
    }));

    expect(characterVersionIntegrityService.invalidRuleIds(version, rules)).toEqual(['modifier', 'poison']);
  });

  it('accepts a version when all references resolve', () => {
    const rules = ['race', 'characteristic', 'ability', 'item', 'modifier', 'state', 'poison', 'sense'].map<Rule>(
      (code) => ({
        id: null,
        code,
        name: code,
        type: 'characteristic',
        description: '',
        spaceId: 2,
        createdAt: '',
      }),
    );

    expect(characterVersionIntegrityService.invalidRuleIds(version, rules)).toEqual([]);
  });

  it('removes unsupported draft references without deleting custom inventory', () => {
    const build: CharacterBuild = {
      name: 'Тест',
      shortDescription: null,
      fullDescription: null,
      spaceId: 2,
      spaceCode: 'actual',
      rulesRevision: 12,
      raceRuleCode: 'race',
      characteristicPurchases: [],
      abilities: [{ ruleCode: 'ability', level: 1 }],
      resources: [{ ruleCode: 'resource', current: { base: 0, size: 0 }, base: { base: 0, size: 0 }, bonuses: [] }],
      inventory: [
        { id: 1, ruleCode: 'item', quantity: 1, equipped: false, modifierRuleCodes: ['modifier'] },
        { id: 2, ruleCode: null, quantity: 1, equipped: false },
      ],
      states: [{ stateRuleCode: 'state', poison: { poisonRuleCode: 'poison' } }],
      money: 0,
      ageYears: null,
      olTotal: 0,
    };

    const cleaned = characterVersionIntegrityService.removeUnsupportedFromBuild(build, [
      'ability',
      'resource',
      'modifier',
      'poison',
    ]);

    expect(cleaned.abilities).toEqual([]);
    expect(cleaned.resources).toEqual([]);
    expect(cleaned.inventory).toHaveLength(2);
    expect(cleaned.inventory[0]?.modifierRuleCodes).toEqual([]);
    expect(cleaned.states[0]?.poison?.poisonRuleCode).toBeNull();
  });
});
