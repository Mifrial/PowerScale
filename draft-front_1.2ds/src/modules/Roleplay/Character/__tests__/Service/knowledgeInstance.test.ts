import { describe, expect, it } from 'vitest';
import { knowledgeInstanceService } from '@/modules/Roleplay/Character/Service/Instance/knowledgeInstanceService';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';

describe('KnowledgeInstanceService', () => {
  it('переписывает физиологию и шаблоны в znanie, схлопывает практику, сидит L1', () => {
    const input: CharacterAbility[] = [
      { ruleCode: 'fiziologiya', level: 2, domain: 'Эльфы', domainCode: 'elf', zone: 'or' },
      { ruleCode: 'ukhod', level: 1, domain: 'Люди', domainCode: 'human', zone: 'or' },
      { ruleCode: 'ukhod', level: 2, domain: 'Эльфы', domainCode: 'elf', zone: 'or' },
      { ruleCode: 'pervaya-pomosch', level: 1, domain: 'Орки', domainCode: 'orc', zone: 'or' },
      { ruleCode: 'predpisaniya-o-lechenii', level: 1, zone: 'or' },
    ];
    const result = knowledgeInstanceService.remapAbilities(input);
    expect(result.some((ability) => ability.ruleCode === 'predpisaniya-o-lechenii')).toBe(false);
    expect(result.filter((ability) => ability.ruleCode === 'ukhod')).toEqual([
      expect.objectContaining({ ruleCode: 'ukhod', level: 2 }),
    ]);
    expect(result.filter((ability) => ability.ruleCode === 'pervaya-pomosch')).toEqual([
      expect.objectContaining({ ruleCode: 'pervaya-pomosch', level: 1 }),
    ]);
    const physiology = result.filter((ability) => ability.ruleCode === 'znanie' && ability.fieldCode === 'physiology');
    expect(physiology.map((ability) => ability.slots?.species)).toEqual(
      expect.arrayContaining([
        { code: 'elf', text: 'Эльфы' },
        { code: 'human', text: 'Люди' },
      ]),
    );
    expect(physiology.find((ability) => ability.slots?.species?.code === 'elf')?.level).toBe(2);
    expect(physiology.find((ability) => ability.slots?.species?.code === 'human')?.level).toBe(1);
  });

  it('собирает слот региона из справочника Раден', () => {
    expect(knowledgeInstanceService.composeSlots('laws', 'Раден', [])).toEqual({
      region: { code: 'raden', text: 'Раден' },
    });
    expect(knowledgeInstanceService.slotInputLabel('laws')).toBe('Регион');
    expect(knowledgeInstanceService.slotOptions('history', []).map((option) => option.name)).toEqual([
      'Раден',
      'Долина десяти тысяч рек',
    ]);
  });

  it('не считает дублем разные типы знания на одном виде', () => {
    const left: CharacterAbility = {
      ruleCode: 'znanie',
      level: 1,
      fieldCode: 'physiology',
      slots: { species: { code: 'elf', text: 'Эльфы' } },
    };
    const right: CharacterAbility = {
      ruleCode: 'znanie',
      level: 1,
      fieldCode: 'diseases',
      slots: { species: { code: 'elf', text: 'Эльфы' } },
    };
    expect(knowledgeInstanceService.sameInstance(left, right)).toBe(false);
  });
});
