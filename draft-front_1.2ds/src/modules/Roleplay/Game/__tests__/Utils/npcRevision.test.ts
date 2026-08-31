import { describe, expect, it } from 'vitest';
import { needsNpcMigration } from '@/modules/Roleplay/Game/Utils/npcRevision';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const game = { rulesRevision: 12, spaceCode: 'actual' };

function version(overrides: Partial<CharacterVersion> = {}): CharacterVersion {
  return {
    name: 'НПС',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'actual',
    rulesRevision: 12,
    raceRuleCode: null,
    characteristics: [],
    resources: [],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
    money: 0,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
    ...overrides,
  };
}

describe('needsNpcMigration', () => {
  it('пустой лист не требует перехода', () => {
    expect(needsNpcMigration({ version: null }, game)).toBe(false);
  });

  it('лист на ревизии игры не требует перехода', () => {
    expect(needsNpcMigration({ version: version() }, game)).toBe(false);
  });

  it('другая ревизия требует перехода', () => {
    expect(needsNpcMigration({ version: version({ rulesRevision: 6 }) }, game)).toBe(true);
  });

  it('другое пространство требует перехода', () => {
    expect(needsNpcMigration({ version: version({ spaceCode: 'razrabotka' }) }, game)).toBe(true);
  });
});
