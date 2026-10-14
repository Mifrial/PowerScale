import { describe, expect, it } from 'vitest';
import { characterHttpViewService } from '@/modules/Roleplay/Character/Service/Instance/characterHttpViewService';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';

describe('CharacterHttpViewService.versionFromStored', () => {
  it('PHP-снимок membership даёт CharacterVersion с массивами', () => {
    const version = characterHttpViewService.versionFromStored({
      name: 'Герой',
      rulesRevision: 1,
      choices: { name: 'Герой', raceCode: 'human', abilities: [] },
      sheet: { characteristicPurchases: [{ characteristicCode: 'strength', cost: 2 }] },
    });

    expect(version).not.toBeNull();
    expect(Array.isArray(version?.characteristics)).toBe(true);
    expect(version?.raceRuleCode).toBe('human');
    expect(version?.rulesRevision).toBe(1);
  });

  it('уже собранную CharacterVersion не ломает', () => {
    const current = characterHttpViewService.versionOf({
      name: 'Герой',
      spaceCode: 'razrabotka',
      rulesRevision: 2,
      choices: { name: 'Герой' },
      sheet: {},
    });
    const roundTrip = characterHttpViewService.versionFromStored(current);

    expect(roundTrip).toBe(current);
  });
});

describe('CharacterHttpViewService.versionOf', () => {
  it('game.getSheets: закупки с value дают ruleCode, base и characteristicPurchases', () => {
    const version = characterHttpViewService.versionOf({
      name: 'Воюн',
      spaceCode: '',
      rulesRevision: 3,
      choices: {
        name: 'Воюн',
        raceCode: 'human',
        characteristicPurchases: [{ characteristicCode: 'awareness', cost: 2 }],
      },
      sheet: {
        characteristicPurchases: [
          { characteristicCode: 'awareness', cost: 2, value: { base: 1, size: 0 } },
          { characteristicCode: 'strength', cost: 2, value: { base: 8, size: 0 } },
        ],
      },
    });

    expect(version.characteristicPurchases).toEqual([
      { characteristicCode: 'awareness', cost: 2 },
      { characteristicCode: 'strength', cost: 2 },
    ]);
    expect(version.characteristics).toEqual([
      { ruleCode: 'awareness', base: { base: 1, size: 0 }, modifiers: [] },
      { ruleCode: 'strength', base: { base: 8, size: 0 }, modifiers: [] },
    ]);
    expect(version.raceRuleCode).toBe('human');
  });
});

describe('CharacterHttpViewService.migratedDetailOf', () => {
  it('conflicts бросает CHARACTER_INVALID с текстами problems', () => {
    expect(() =>
      characterHttpViewService.migratedDetailOf({
        kind: 'conflicts',
        problems: [
          { code: 'CHARACTER_RACE', message: 'Раса не собралась', stage: 'assemble' },
          { code: 'CHARACTER_LIMIT', message: 'Предел ОС', stage: 'assemble' },
        ],
      }),
    ).toThrow(CharacterApiError);
    try {
      characterHttpViewService.migratedDetailOf({
        kind: 'conflicts',
        problems: [{ code: 'CHARACTER_RACE', message: 'Раса не собралась', stage: 'assemble' }],
      });
    } catch (error) {
      expect(error).toBeInstanceOf(CharacterApiError);
      expect((error as CharacterApiError).code).toBe('CHARACTER_INVALID');
      expect((error as CharacterApiError).message).toBe('Раса не собралась');
    }
  });

  it('ok собирает CharacterDetail', () => {
    const detail = characterHttpViewService.migratedDetailOf({
      kind: 'ok',
      id: 3,
      name: 'Воюн',
      spaceId: 1,
      revision: 4,
      active: true,
      actualVersion: 5,
      choices: { name: 'Воюн', raceCode: 'human' },
      sheet: {},
    });

    expect(detail.character.id).toBe(3);
    expect(detail.character.rulesRevision).toBe(4);
    expect(detail.actualVersion).toBe(5);
  });
});
