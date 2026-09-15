import { describe, it, expect } from 'vitest';
import { mockLanguages } from '@/modules/Roleplay/Rule/Mock/mockLanguages';
import { mockEthnicities } from '@/modules/Roleplay/Rule/Mock/mockEthnicities';
import { ethnicityPickService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicityPickService';
import { nativeLanguageService } from '@/modules/Roleplay/Character/Service/Instance/nativeLanguageService';
import { skillStudyUnlockService } from '@/modules/Roleplay/Character/Service/Instance/skillStudyUnlockService';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';

function build(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 1,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
    ...overrides,
  };
}

describe('EthnicityPickService', () => {
  const rules = [...mockLanguages, ...mockEthnicities];

  it('Раден и Содружество не выбираются, СРИ и Форн — да', () => {
    const options = ethnicityPickService.ethnicityOptions(rules, null);
    expect(options.some((option) => option.code === 'sri')).toBe(true);
    expect(options.some((option) => option.code === 'forn')).toBe(true);
    expect(options.some((option) => option.code === 'raden')).toBe(false);
    expect(options.some((option) => option.code === 'evs')).toBe(false);
  });

  it('у улаев родной улай\'тиль сверху', () => {
    const options = ethnicityPickService.nativeLanguageOptions(rules, 'ulay');
    expect(options[0]?.code).toBe('ulay-til');
    expect(options.some((option) => option.code === 'ar-til' && option.preferred)).toBe(true);
  });

  it('combobox-объект резолвится в код, свободная строка — в текст', () => {
    const options = ethnicityPickService.ethnicityOptions(rules, null);
    expect(ethnicityPickService.matchOption({ value: 'sri' }, options, (option) => [option.code])).toEqual({
      code: 'sri',
      text: null,
    });
    expect(ethnicityPickService.matchOption('Своё племя', options, (option) => [option.code, option.name])).toEqual({
      code: null,
      text: 'Своё племя',
    });
  });

  it('краткий абзац описания и отображаемое имя, не код', () => {
    const options = ethnicityPickService.ethnicityOptions(rules, null);
    const ulay = options.find((option) => option.code === 'ulay');
    expect(ulay?.subtitle).toMatch(/улай/i);
    expect(ethnicityPickService.displayLabel('ulay', null, options)).toBe('Улай');
  });

  it('рекомендуемые отделены чертой, остальные остаются в списке', () => {
    const items = ethnicityPickService.comboItems([
      { code: 'ulay', name: "Улай", preferred: true },
      { code: 'sri', name: 'Священная Империя Раден', preferred: false },
    ]);
    expect(items.map((item) => item.type ?? item.value)).toEqual(['ulay', 'divider', 'sri']);
  });
});

describe('NativeLanguageService', () => {
  const rules = [...mockLanguages, ...mockEthnicities];

  it('ставит дар владения 2 и переносит при смене языка', () => {
    const first = nativeLanguageService.syncNativeSpeech(build({ nativeLanguageCode: 'rados' }), rules);
    expect(first.abilities).toEqual([
      expect.objectContaining({
        ruleCode: 'vladenie-yazykom',
        level: 2,
        domainCode: 'rados',
        gifted: true,
      }),
    ]);
    const moved = nativeLanguageService.syncNativeSpeech(
      { ...first, nativeLanguageCode: 'ulay-til' },
      rules,
    );
    expect(moved.abilities).toHaveLength(1);
    expect(moved.abilities[0]?.domainCode).toBe('ulay-til');
    expect(moved.abilities[0]?.level).toBe(2);
  });

  it('свой текст не перехватывает чужой экземпляр без domainCode', () => {
    const sheet = build({
      nativeLanguageText: 'Домашний',
      abilities: [
        { ruleCode: 'vladenie-yazykom', level: 3, domain: 'Чужой', gifted: false },
      ],
    });
    const next = nativeLanguageService.syncNativeSpeech(sheet, rules);
    expect(next.abilities).toHaveLength(2);
    expect(next.abilities.find((ability) => ability.domain === 'Чужой')?.gifted).toBeFalsy();
    expect(next.abilities.find((ability) => ability.domain === 'Домашний')).toEqual(
      expect.objectContaining({ gifted: true, level: 2, domainCode: null }),
    );
  });

  it('сток Локс не становится кодом родного языка', () => {
    const next = nativeLanguageService.syncNativeSpeech(build({ nativeLanguageCode: 'locx' }), rules);
    expect(next.nativeLanguageCode).toBeNull();
    expect(next.nativeLanguageText).toBe('Локс');
    expect(next.abilities[0]?.domainCode).toBeNull();
    expect(next.abilities[0]?.domain).toBe('Локс');
  });
});

describe('SkillStudyUnlockService', () => {
  it('первый экземпляр письменности бесплатен при гранте червя', () => {
    const unlocks = [
      { type: 'skill_study' as const, ability_codes: ['znanie', 'pismennost'], max_level: 1, paid_cost: 0, max_instances: 1 },
    ];
    const writing: CharacterAbility = {
      ruleCode: 'pismennost',
      level: 1,
      domain: 'Раденский алфавит',
      domainCode: 'raden-alphabet',
    };
    expect(skillStudyUnlockService.paidRungsFree(writing, unlocks, [writing])).toBe(1);
    const second: CharacterAbility = {
      ruleCode: 'pismennost',
      level: 1,
      domain: 'Гаргатский алфавит',
      domainCode: 'gargat-alphabet',
    };
    expect(skillStudyUnlockService.paidRungsFree(second, unlocks, [writing, second])).toBe(0);
  });
});
