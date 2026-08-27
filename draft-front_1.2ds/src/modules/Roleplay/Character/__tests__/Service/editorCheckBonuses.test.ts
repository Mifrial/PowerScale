import { describe, expect, it } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { editorCheckBonusesService } from '@/modules/Roleplay/Character/Service/Instance/editorCheckBonusesService';

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    spaceId: 1,
    raceRuleId: '',
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

describe('EditorCheckBonusesService', () => {
  it('показывает бонус Скрытности от активной способности', () => {
    const stealth = ruleCatalog.find((rule) => rule.code === 'skrytnost')!;
    const bonuses = editorCheckBonusesService.build(
      makeBuild({ abilities: [{ ruleId: stealth.id, level: 1 }] }),
      ruleCatalog,
    );

    expect(bonuses).toEqual([
      expect.objectContaining({
        checkCode: 'stealth',
        delta: 2,
        modifiers: [expect.objectContaining({ sourceRuleId: stealth.id, delta: 2 })],
      }),
    ]);
  });

  it('не показывает бонусы от неактивной способности', () => {
    const stealth = ruleCatalog.find((rule) => rule.code === 'skrytnost')!;

    expect(
      editorCheckBonusesService.build(makeBuild({ abilities: [{ ruleId: stealth.id, level: 0 }] }), ruleCatalog),
    ).toEqual([]);
  });
});
