import { describe, expect, it } from 'vitest';
import { characterHttpViewService } from '@/modules/Roleplay/Character/Service/Instance/characterHttpViewService';

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
