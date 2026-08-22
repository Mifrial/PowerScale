import { describe, it, expect } from 'vitest';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const baseRule = (id: string, code: string, type: Rule['type'], spec?: any, spaceId = 1): Rule => ({
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
      baseRule('c1', 'str', 'characteristic'),
      baseRule('c2', 'wiz', 'characteristic', { formula: {} }),
      baseRule('c3', 'str-other', 'characteristic', undefined, 2),
    ];
    expect(ruleReferenceService.characteristicOptions(rules, 1)).toEqual([{ code: 'str', name: 'str' }]);
  });
});

describe('RuleReferenceService.resourceOptions', () => {
  it('проставляет isDimensional по спеку ресурса', () => {
    const rules = [baseRule('r1', 'mana', 'resource'), baseRule('r2', 'hp', 'resource', { is_dimensional: true })];
    expect(ruleReferenceService.resourceOptions(rules)).toEqual([
      { code: 'mana', name: 'mana', isDimensional: false },
      { code: 'hp', name: 'hp', isDimensional: true },
    ]);
  });
});

describe('RuleReferenceService.abilityOptions / sourceOptions / zoneOptions', () => {
  it('фильтруют правила по типу', () => {
    const rules = [
      baseRule('a1', 'bash', 'ability'),
      baseRule('s1', 'cast', 'source'),
      baseRule('z1', 'core', 'points'),
    ];
    expect(ruleReferenceService.abilityOptions(rules)).toEqual([{ code: 'bash', name: 'bash' }]);
    expect(ruleReferenceService.sourceOptions(rules)).toEqual([{ code: 'cast', name: 'cast' }]);
    expect(ruleReferenceService.zoneOptions(rules)).toEqual([{ label: 'core', value: 'core' }]);
  });
});

describe('RuleReferenceService.itemOptions', () => {
  it('возвращает только предметы', () => {
    const rules = [baseRule('i1', 'sword', 'item'), baseRule('a1', 'bash', 'ability')];
    expect(ruleReferenceService.itemOptions(rules)).toEqual([{ code: 'sword', name: 'sword' }]);
  });
});

describe('RuleReferenceService.speciesOptions', () => {
  it('исключает правило с excludeRuleId', () => {
    const rules = [baseRule('sp1', 'human', 'species'), baseRule('sp2', 'elf', 'species')];
    expect(ruleReferenceService.speciesOptions(rules, 'sp1')).toEqual([{ code: 'elf', name: 'elf' }]);
    expect(ruleReferenceService.speciesOptions(rules)).toEqual([
      { code: 'human', name: 'human' },
      { code: 'elf', name: 'elf' },
    ]);
  });
});

describe('RuleReferenceService.abilityNameMap', () => {
  it('собирает name только для способностей', () => {
    const rules = [
      baseRule('a1', 'bash', 'ability'),
      baseRule('a2', 'focus', 'ability'),
      baseRule('s1', 'cast', 'source'),
    ];
    expect(ruleReferenceService.abilityNameMap(rules)).toEqual(
      new Map([
        ['bash', 'bash'],
        ['focus', 'focus'],
      ]),
    );
  });
});
