import { describe, expect, it } from 'vitest';
import { knowledgeCheckService } from '@/modules/Roleplay/Character/Service/Instance/knowledgeCheckService';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { KNOWLEDGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_ABILITY_CODE';
import { LAW_DEFENSE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/LAW_DEFENSE_ABILITY_CODE';

function species(code: string, name: string, keywordIds: number[]): Rule {
  return {
    id: null,
    code,
    type: 'species',
    name,
    description: '',
    spaceId: 1,
    keywordIds,
    createdAt: 1,
  };
}

const rules: Rule[] = [species('human', 'Люди', [17]), species('elf', 'Эльфы', [17]), species('wolf', 'Волки', [99])];

function knowledge(fieldCode: string, level: number, slots: CharacterAbility['slots']): CharacterAbility {
  return { ruleCode: KNOWLEDGE_ABILITY_CODE, level, fieldCode, slots };
}

describe('KnowledgeCheckService', () => {
  it('точный код вида; trim текстов; код vs текст без кода — не матч; разные типы не суммируются', () => {
    const abilities = [
      knowledge('physiology', 2, { species: { code: 'elf', text: 'Эльфы' } }),
      knowledge('diseases', 3, { species: { code: 'elf', text: 'Эльфы' } }),
      knowledge('history', 2, { region: { code: null, text: '  Раден  ' } }),
    ];
    expect(
      knowledgeCheckService.effectiveLevel(
        abilities,
        'physiology',
        { species: { code: 'elf', text: 'другие эльфы' } },
        rules,
      ),
    ).toBe(2);
    expect(
      knowledgeCheckService.effectiveLevel(abilities, 'history', { region: { code: null, text: 'Раден' } }, rules),
    ).toBe(2);
    expect(
      knowledgeCheckService.effectiveLevel(abilities, 'physiology', { species: { code: null, text: 'Эльфы' } }, rules),
    ).toBe(0);
    expect(
      knowledgeCheckService.effectiveLevel(abilities, 'physiology', { species: { code: 'elf', text: 'Эльфы' } }, rules),
    ).toBe(2);
  });

  it('близкий humanoid −1; без кода — 0; регион не шарится; точный матч без −1', () => {
    const abilities = [
      knowledge('physiology', 3, { species: { code: 'human', text: 'Люди' } }),
      knowledge('plants', 3, { region: { code: 'raden', text: 'Раден' } }),
    ];
    expect(
      knowledgeCheckService.effectiveLevel(abilities, 'physiology', { species: { code: 'elf', text: 'Эльфы' } }, rules),
    ).toBe(2);
    expect(
      knowledgeCheckService.effectiveLevel(abilities, 'physiology', { species: { code: null, text: 'Эльфы' } }, rules),
    ).toBe(0);
    expect(
      knowledgeCheckService.effectiveLevel(
        abilities,
        'plants',
        { region: { code: 'dolina-desyati-tysyach-rek', text: 'Долина' } },
        rules,
      ),
    ).toBe(0);
    expect(
      knowledgeCheckService.effectiveLevel(
        abilities,
        'physiology',
        { species: { code: 'human', text: 'Люди' } },
        rules,
      ),
    ).toBe(3);
    expect(
      knowledgeCheckService.effectiveLevel(
        abilities,
        'physiology',
        { species: { code: 'wolf', text: 'Волки' } },
        rules,
      ),
    ).toBe(0);
  });

  it('раса цели сводится к виду: дворфы 3 vs человек-раса → 2; vs дворф-раса → 3', () => {
    const catalog: Rule[] = [
      species('human', 'Люди', [17]),
      species('dwarves', 'Дворфы', [17]),
      {
        id: null,
        code: 'kogir',
        type: 'race',
        name: 'Когир',
        description: '',
        spaceId: 1,
        spec: { parent_race_code: 'human', cost_os: 0, characteristics: [], abilities: [] },
        keywordIds: [17, 21, 27],
        createdAt: 1,
      },
      {
        id: null,
        code: 'turim',
        type: 'race',
        name: 'Турим',
        description: '',
        spaceId: 1,
        spec: { parent_race_code: 'dwarves', cost_os: 0, characteristics: [], abilities: [] },
        keywordIds: [17, 32, 34],
        createdAt: 1,
      },
    ];
    const abilities = [knowledge('physiology', 3, { species: { code: 'dwarves', text: 'Дворфы' } })];
    expect(
      knowledgeCheckService.effectiveLevel(
        abilities,
        'physiology',
        { species: { code: 'kogir', text: 'Когир' } },
        catalog,
      ),
    ).toBe(2);
    expect(
      knowledgeCheckService.effectiveLevel(
        abilities,
        'physiology',
        { species: { code: 'turim', text: 'Турим' } },
        catalog,
      ),
    ).toBe(3);
  });

  it('{2|0} + нехватка 1 → {2|1}', () => {
    expect(knowledgeCheckService.raisedDifficulty({ base: 2, size: 0 }, 1)).toEqual({ base: 2, size: 1 });
  });

  it('полнота ответа: провал, полный, неполный; болезни без физиологии', () => {
    expect(
      knowledgeCheckService.answerCompleteness({
        success: false,
        shortage: 2,
        fieldCode: 'history',
        physiologyLevel: 0,
      }),
    ).toBe('fail');
    expect(
      knowledgeCheckService.answerCompleteness({
        success: true,
        shortage: 0,
        fieldCode: 'history',
        physiologyLevel: 0,
      }),
    ).toBe('full');
    expect(
      knowledgeCheckService.answerCompleteness({
        success: true,
        shortage: 1,
        fieldCode: 'history',
        physiologyLevel: 0,
      }),
    ).toBe('incomplete');
    expect(
      knowledgeCheckService.answerCompleteness({
        success: true,
        shortage: 0,
        fieldCode: 'diseases',
        physiologyLevel: 0,
      }),
    ).toBe('incomplete');
  });

  it('защита от закона только laws + известный регион', () => {
    const abilities: CharacterAbility[] = [
      knowledge('laws', 2, { region: { code: 'raden', text: 'Раден' } }),
      { ruleCode: LAW_DEFENSE_ABILITY_CODE, level: 2 },
    ];
    expect(knowledgeCheckService.lawDefenseDelta(abilities, 'laws', { code: 'raden', text: 'Раден' })).toBe(2);
    expect(knowledgeCheckService.lawDefenseDelta(abilities, 'history', { code: 'raden', text: 'Раден' })).toBe(0);
    expect(
      knowledgeCheckService.lawDefenseDelta(abilities, 'laws', {
        code: 'dolina-desyati-tysyach-rek',
        text: 'Долина',
      }),
    ).toBe(0);
  });

  it('ворота практики: уход без физиологии false; фармация без растений false; перевязка не в функции', () => {
    const empty: CharacterAbility[] = [];
    expect(
      knowledgeCheckService.practiceApplies('ukhod', empty, { targetSpecies: { code: 'human', text: 'Люди' } }, rules),
    ).toBe(false);
    const physiologyOnly = [knowledge('physiology', 1, { species: { code: 'human', text: 'Люди' } })];
    expect(
      knowledgeCheckService.practiceApplies(
        'farmatsiya',
        physiologyOnly,
        {
          targetSpecies: { code: 'human', text: 'Люди' },
          placeRegion: { code: 'raden', text: 'Раден' },
        },
        rules,
      ),
    ).toBe(false);
    expect(
      knowledgeCheckService.practiceApplies(
        'pervaya-pomosch',
        physiologyOnly,
        { targetSpecies: { code: 'human', text: 'Люди' } },
        rules,
      ),
    ).toBe(false);
    expect(
      knowledgeCheckService.practiceApplies(
        'smertonosnye-udary',
        physiologyOnly,
        { targetSpecies: { code: 'human', text: 'Люди' } },
        rules,
      ),
    ).toBe(false);
  });
});
