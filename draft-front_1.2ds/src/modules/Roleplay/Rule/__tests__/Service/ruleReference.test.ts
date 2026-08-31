import { describe, it, expect } from 'vitest';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const baseRule = (id: number | null, code: string, type: Rule['type'], spec?: any, spaceId = 1): Rule => ({
  id,
  code,
  type,
  name: code,
  description: '',
  spaceId,
  spec,
  createdAt: '2026-01-01T00:00:00Z',
});

describe('RuleReferenceService.characteristicOptions', () => {
  it('исключает формульные характеристики и правила других пространств', () => {
    const rules = [
      baseRule(null, 'str', 'characteristic'),
      baseRule(null, 'wiz', 'characteristic', { formula: {} }),
      baseRule(null, 'str-other', 'characteristic', undefined, 2),
    ];
    expect(ruleReferenceService.characteristicOptions(rules, 1)).toEqual([{ code: 'str', name: 'str' }]);
  });
});

describe('RuleReferenceService.resourceOptions', () => {
  it('проставляет isDimensional по спеку ресурса', () => {
    const rules = [baseRule(null, 'mana', 'resource'), baseRule(null, 'hp', 'resource', { is_dimensional: true })];
    expect(ruleReferenceService.resourceOptions(rules)).toEqual([
      { code: 'mana', name: 'mana', isDimensional: false },
      { code: 'hp', name: 'hp', isDimensional: true },
    ]);
  });
});

describe('RuleReferenceService.abilityOptions / sourceOptions / zoneOptions', () => {
  it('фильтруют правила по типу', () => {
    const rules = [
      baseRule(null, 'bash', 'ability'),
      baseRule(null, 'cast', 'source'),
      baseRule(null, 'core', 'points'),
    ];
    expect(ruleReferenceService.abilityOptions(rules)).toEqual([{ code: 'bash', name: 'bash' }]);
    expect(ruleReferenceService.sourceOptions(rules)).toEqual([{ code: 'cast', name: 'cast' }]);
    expect(ruleReferenceService.zoneOptions(rules)).toEqual([{ label: 'core', value: 'core' }]);
  });
});

describe('RuleReferenceService.itemOptions', () => {
  it('возвращает только предметы', () => {
    const rules = [baseRule(null, 'sword', 'item'), baseRule(null, 'bash', 'ability')];
    expect(ruleReferenceService.itemOptions(rules)).toEqual([{ code: 'sword', name: 'sword' }]);
  });
});

describe('RuleReferenceService.speciesOptions', () => {
  it('исключает правило с excludeCode', () => {
    const rules = [baseRule(null, 'human', 'species'), baseRule(null, 'elf', 'species')];
    expect(ruleReferenceService.speciesOptions(rules, 'human')).toEqual([{ code: 'elf', name: 'elf' }]);
    expect(ruleReferenceService.speciesOptions(rules)).toEqual([
      { code: 'human', name: 'human' },
      { code: 'elf', name: 'elf' },
    ]);
  });
});

describe('RuleReferenceService.abilityNameMap', () => {
  it('собирает name только для способностей', () => {
    const rules = [
      baseRule(null, 'bash', 'ability'),
      baseRule(null, 'focus', 'ability'),
      baseRule(null, 'cast', 'source'),
    ];
    expect(ruleReferenceService.abilityNameMap(rules)).toEqual(
      new Map([
        ['bash', 'bash'],
        ['focus', 'focus'],
      ]),
    );
  });
});
