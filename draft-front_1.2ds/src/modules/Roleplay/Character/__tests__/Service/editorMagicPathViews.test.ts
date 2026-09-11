import { describe, expect, it } from 'vitest';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import { EditorMagicPathViewsService } from '@/modules/Roleplay/Character/Service/EditorMagicPathViewsService';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { keywords } from '@/modules/Roleplay/Keyword/Mock/mockKeywords';

const service = new EditorMagicPathViewsService();

function makeBuild(abilities: CharacterBuild['abilities']): CharacterBuild {
  return {
    name: 'Тест',
    shortDescription: null,
    fullDescription: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities,
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
  };
}

describe('EditorMagicPathViewsService', () => {
  it('становление открывает Арканиста: заклинания до 2', () => {
    const views = service.build(makeBuild([{ ruleCode: 'becoming-arcanist', level: 1 }]), ruleCatalog, keywords);
    expect(views).toEqual([
      expect.objectContaining({
        pathCode: 'arcanist',
        pathName: 'Арканист',
        restrictionLabels: ['Заклинания с базовой стоимостью до 2'],
      }),
    ]);
  });

  it('подмена структур добавляет потолок волшебства', () => {
    const views = service.build(
      makeBuild([
        { ruleCode: 'becoming-arcanist', level: 1 },
        { ruleCode: 'structure-substitution', level: 1 },
      ]),
      ruleCatalog,
      keywords,
    );
    expect(views[0]?.restrictionLabels).toEqual([
      'Заклинания с базовой стоимостью до 2',
      'Волшебство со стоимостью до 2',
    ]);
  });

  it('пробуждение показывает Псионика без гранта пути', () => {
    const views = service.build(makeBuild([{ ruleCode: 'psionic-awakening', level: 1 }]), ruleCatalog, keywords);
    expect(views).toEqual([
      expect.objectContaining({
        pathCode: 'psionic',
        restrictionLabels: ['Заклинания с базовой стоимостью до 1'],
      }),
    ]);
  });

  it('потолки арканиста и псионика не смешиваются', () => {
    const views = service.build(
      makeBuild([
        { ruleCode: 'becoming-arcanist', level: 1 },
        { ruleCode: 'structure-substitution', level: 1 },
        { ruleCode: 'psionic-awakening', level: 1 },
        { ruleCode: 'psionic-control', level: 1 },
      ]),
      ruleCatalog,
      keywords,
    );
    const byCode = new Map(views.map((view) => [view.pathCode, view]));
    expect(byCode.get('arcanist')?.restrictionLabels).toEqual([
      'Заклинания с базовой стоимостью до 2',
      'Волшебство со стоимостью до 2',
    ]);
    expect(byCode.get('psionic')?.restrictionLabels).toEqual([
      'Заклинания с базовой стоимостью до 1',
      'Волшебство со стоимостью до 1',
    ]);
  });
});
