import { describe, expect, it } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import { characterSheetValidationIssues } from '@/modules/Roleplay/Character/Utils/characterSheetValidation';

function budget(exceeded = false) {
  return { total: 10, spent: exceeded ? 11 : 0, exceeded };
}

function model(over: Partial<CharacterEditorModel['budgets']> = {}): CharacterEditorModel {
  return {
    abilities: [],
    budgets: {
      os: budget(),
      ol: budget(),
      or: budget(),
      money: budget(),
      ...over,
    },
  } as unknown as CharacterEditorModel;
}

function build(over: Partial<CharacterBuild> = {}): CharacterBuild {
  return {
    name: 'Торвин',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleId: 'race-1',
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
    ...over,
  };
}

describe('characterSheetValidationIssues', () => {
  it('без черновика или модели — нельзя сохранить', () => {
    expect(characterSheetValidationIssues(undefined, model(), true)).toEqual(['Черновик не загружен']);
    expect(characterSheetValidationIssues(build(), null, true)).toEqual(['Черновик не загружен']);
  });

  it('пустое имя и нет расы', () => {
    expect(characterSheetValidationIssues(build({ name: '  ', raceRuleId: null }), model(), true)).toEqual([
      'Не задано имя',
      'Не выбрана раса',
    ]);
  });

  it('НПС без обязательной расы проходит по расе', () => {
    expect(characterSheetValidationIssues(build({ raceRuleId: null }), model(), false)).toEqual([]);
  });

  it('превышение бюджетов', () => {
    expect(characterSheetValidationIssues(build(), model({ os: budget(true), money: budget(true) }), true)).toEqual([
      'Превышен лимит ОС',
      'Превышен бюджет денег',
    ]);
  });

  it('валидный лист — без проблем', () => {
    expect(characterSheetValidationIssues(build(), model(), true)).toEqual([]);
  });
});
