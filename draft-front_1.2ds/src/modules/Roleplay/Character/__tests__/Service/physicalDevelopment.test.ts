import { describe, it, expect } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';

const service = new CharacterEditorService();
const config: CharacterCreationConfig = { osTotal: 20, orTotal: 40, moneyBudget: 100 };
const keywords: Keyword[] = [{ id: 61, code: 'section-body', name: 'Тело', active: true }];

function makeBuild(overrides: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: ruleCatalog.find((rule) => rule.code === 'alierets')?.code ?? null,
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

const physId = () => ruleCatalog.find((rule) => rule.code === 'fizicheskoe-razvitie')?.code ?? '';

function param(base: number) {
  return { base, size: 0 };
}

describe('Физическое развитие (пул +9)', () => {
  it('ровно +3/+3/+3 стоит 21 ОР, уровень 9, потолок одной хар-ки 6', () => {
    let build = makeBuild();
    build = characterBuildService.setAbilityParameter(build, physId(), 'strength', 3, ruleCatalog);
    build = characterBuildService.setAbilityParameter(build, physId(), 'endurance', 3, ruleCatalog);
    build = characterBuildService.setAbilityParameter(build, physId(), 'dexterity', 3, ruleCatalog);
    const model = service.build(build, ruleCatalog, config, keywords);
    const ability = model.abilities.find((entry) => entry.code === 'fizicheskoe-razvitie');
    expect(ability?.level).toBe(9);
    expect(ability?.parameters.map((entry) => [entry.code, entry.value.base, entry.max.base])).toEqual([
      ['strength', 3, 3],
      ['endurance', 3, 3],
      ['dexterity', 3, 3],
    ]);
    expect(model.budgets.or.spent).toBe(21);
    expect(model.characteristics.find((entry) => entry.code === 'strength')?.modifiers.some((m) => m.delta === 3)).toBe(
      true,
    );
  });

  it('сумма не выше 9: шестой пункт третьей хар-ки сжимает max', () => {
    let build = makeBuild();
    build = characterBuildService.setAbilityParameter(build, physId(), 'strength', 6, ruleCatalog);
    build = characterBuildService.setAbilityParameter(build, physId(), 'endurance', 3, ruleCatalog);
    build = characterBuildService.setAbilityParameter(build, physId(), 'dexterity', 1, ruleCatalog);
    expect(build.abilities[0]?.parameters?.dexterity).toEqual(param(0));
    const model = service.build(build, ruleCatalog, config, keywords);
    const ability = model.abilities.find((entry) => entry.code === 'fizicheskoe-razvitie');
    expect(ability?.parameters.find((entry) => entry.code === 'dexterity')?.max).toEqual(param(0));
  });

  it('каждый второй пункт Силы даёт +1 Вес от тренировок', () => {
    const weightOf = (strength: number): number => {
      let build = makeBuild();
      build = characterBuildService.setAbilityParameter(build, physId(), 'strength', strength, ruleCatalog);
      const model = service.build(build, ruleCatalog, config, keywords);

      return (
        model.characteristics.find((entry) => entry.code === 'weight')?.modifiers.find((m) => m.delta > 0)?.delta ?? 0
      );
    };
    expect(weightOf(1)).toBe(0);
    expect(weightOf(2)).toBe(1);
    expect(weightOf(3)).toBe(1);
    expect(weightOf(4)).toBe(2);
  });

  it('Тренировка скорости доступна с одним пунктом физического развития', () => {
    let build = makeBuild();
    build = characterBuildService.setAbilityParameter(build, physId(), 'dexterity', 1, ruleCatalog);
    const model = service.build(build, ruleCatalog, config, keywords);
    const speed = model.abilities.find((entry) => entry.code === 'trenirovka-skorosti');
    expect(speed?.levels[0]?.met).toBe(true);
  });
});
