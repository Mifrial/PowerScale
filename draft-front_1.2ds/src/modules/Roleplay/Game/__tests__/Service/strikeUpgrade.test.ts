import { describe, expect, it } from 'vitest';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { KNOWLEDGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_ABILITY_CODE';
import { strikeUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/strikeUpgradeService';
import { knowledgeCheckService } from '@/modules/Roleplay/Character/init';

const deadly: Rule = {
  id: null,
  code: 'smertonosnye-udary',
  type: 'ability',
  name: 'Смертоносные удары',
  description: '',
  spaceId: 1,
  spec: {
    type: 'skill',
    zones: { or: { kind: 'array', levels_cost: [1] } },
    requirements: [],
    grants: [],
    parent_ability_code: 'khirurgiya',
    strike_upgrade: {
      exclusive_group: 'smertonosnye-udary',
      requires_physiology: true,
      modes: [
        { code: 'cripple', label: 'Калечить', injury_check_advantage: 1 },
        { code: 'spare', label: 'Щадить', injury_check_advantage: -1 },
      ],
    },
  },
  createdAt: 1,
};

function species(code: string, keywordIds: number[]): Rule {
  return {
    id: null,
    code,
    type: 'species',
    name: code,
    description: '',
    spaceId: 1,
    keywordIds,
    createdAt: 1,
  };
}

const rules: Rule[] = [
  deadly,
  species('human', [17]),
  species('dwarves', [17]),
  species('elf', [17]),
  species('wolf', [99]),
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
];

function version(raceRuleCode: string | null, abilities: CharacterAbility[]): CharacterVersion {
  return {
    name: 'Цель',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'locx',
    rulesRevision: 1,
    raceRuleCode,
    characteristics: [],
    resources: [],
    abilities,
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 0 },
    money: 0,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
  };
}

function actorAbilities(physiologyCode: string | null): CharacterAbility[] {
  const abilities: CharacterAbility[] = [{ ruleCode: 'smertonosnye-udary', level: 1 }];
  if (physiologyCode) {
    abilities.push({
      ruleCode: KNOWLEDGE_ABILITY_CODE,
      level: 2,
      fieldCode: 'physiology',
      slots: { species: { code: physiologyCode, text: '' } },
    });
  }

  return abilities;
}

describe('StrikeUpgradeService', () => {
  it('без физиологии вида галок нет; без кода расы тоже', () => {
    const skillOnly = actorAbilities(null);
    expect(strikeUpgradeService.listApplicable(skillOnly, version('elf', []), rules)).toEqual([]);
    const withKnowledge = actorAbilities('elf');
    expect(strikeUpgradeService.listApplicable(withKnowledge, version(null, []), rules)).toEqual([]);
    expect(
      knowledgeCheckService.practiceApplies(
        'smertonosnye-udary',
        withKnowledge,
        { targetSpecies: { code: 'elf', text: '' } },
        rules,
      ),
    ).toBe(false);
  });

  it('близкий вид открывает режимы; точный тоже', () => {
    const abilities = actorAbilities('human');
    expect(
      strikeUpgradeService.listApplicable(abilities, version('elf', []), rules).map((item) => item.optionId),
    ).toEqual(['smertonosnye-udary:cripple', 'smertonosnye-udary:spare']);
    expect(strikeUpgradeService.listApplicable(abilities, version('wolf', []), rules)).toEqual([]);
  });

  it('физиология дворфов открывает удар по расе человека', () => {
    const abilities = actorAbilities('dwarves');
    expect(
      strikeUpgradeService.listApplicable(abilities, version('kogir', []), rules).map((item) => item.optionId),
    ).toEqual(['smertonosnye-udary:cripple', 'smertonosnye-udary:spare']);
  });

  it('калечить +1, щадить −1; оба после prune — один; ноль допустим', () => {
    const abilities = actorAbilities('elf');
    const applicable = strikeUpgradeService.listApplicable(abilities, version('elf', []), rules);
    expect(strikeUpgradeService.pruneSelected(applicable, [])).toEqual([]);
    expect(
      strikeUpgradeService.pruneSelected(applicable, ['smertonosnye-udary:cripple', 'smertonosnye-udary:spare']),
    ).toEqual(['smertonosnye-udary:cripple']);
    const cripple = strikeUpgradeService.selectedOf(applicable, ['smertonosnye-udary:cripple']);
    expect(strikeUpgradeService.injuryAdvantageModifiers(cripple)).toEqual([
      { source_code: 'smertonosnye-udary:cripple', source_label: 'Смертоносные удары · Калечить', delta: 1 },
    ]);
    const spare = strikeUpgradeService.selectedOf(applicable, ['smertonosnye-udary:spare']);
    expect(strikeUpgradeService.injuryAdvantageModifiers(spare)).toEqual([
      { source_code: 'smertonosnye-udary:spare', source_label: 'Смертоносные удары · Щадить', delta: -1 },
    ]);
    const mixed = strikeUpgradeService.withInjuryAdvantages(
      { leftoverDamage: 2, woundStrength: 1, endurance: 4, exhaustion: 0, attackSr: 0 },
      abilities,
      version('elf', []),
      ['smertonosnye-udary:cripple', 'smertonosnye-udary:spare'],
      rules,
    );
    expect(mixed.advantages).toEqual([
      { source_code: 'smertonosnye-udary:cripple', source_label: 'Смертоносные удары · Калечить', delta: 1 },
    ]);
  });
});
