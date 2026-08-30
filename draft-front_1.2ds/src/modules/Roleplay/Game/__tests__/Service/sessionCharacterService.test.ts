import { describe, expect, it } from 'vitest';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { reactive } from 'vue';

const version = (partial: Partial<CharacterVersion> = {}): CharacterVersion =>
  ({
    name: 'Герой',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'actual',
    rulesRevision: 12,
    raceRuleId: null,
    characteristics: [],
    resources: [{ ruleId: 'rule-18', current: { base: 3, size: 0 }, base: { base: 3, size: 0 }, bonuses: [] }],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
    money: 10,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
    ...partial,
  }) as CharacterVersion;

const overlay = (partial: Partial<GameCombatOverlay> = {}): GameCombatOverlay => ({
  gameId: 1,
  entityKey: 'character:1',
  kind: 'character',
  resources: [],
  states: [],
  updatedAt: '',
  ...partial,
});

describe('SessionCharacterService', () => {
  it('resolve без overlay возвращает approved', () => {
    const approved = version();
    expect(sessionCharacterService.resolve(approved, null)?.money).toBe(10);
  });

  it('resolve клонирует Vue-прокси approved', () => {
    const approved = reactive(version());
    expect(sessionCharacterService.resolve(approved, null)?.money).toBe(10);
  });

  it('resolve мержит боевой overlay на approved', () => {
    const approved = version();
    const result = sessionCharacterService.resolve(
      approved,
      overlay({
        updatedAt: '2026-08-30T10:00:00Z',
        resources: [{ ruleId: 'rule-18', current: { base: 1, size: 0 } }],
        states: [{ stateRuleId: 'rule-63', value: 5 }],
      }),
    );
    expect(result?.resources[0]?.current).toEqual({ base: 1, size: 0 });
    expect(result?.states).toEqual([{ stateRuleId: 'rule-63', value: 5 }]);
  });

  it('stripIdentity не даёт overlay сменить spaceCode и rulesRevision', () => {
    const approved = version({ spaceCode: 'actual', rulesRevision: 12 });
    const sheet = version({ spaceCode: 'hack', rulesRevision: 1, money: 99 });
    const stripped = sessionCharacterService.stripIdentity(sheet, approved);
    expect(stripped.spaceCode).toBe('actual');
    expect(stripped.rulesRevision).toBe(12);
    expect(stripped.money).toBe(99);
  });

  it('needsModeration при пустом approved и при diff', () => {
    expect(sessionCharacterService.needsModeration(null, version())).toBe(true);
    expect(sessionCharacterService.needsModeration(version(), version())).toBe(false);
    expect(sessionCharacterService.needsModeration(version(), version({ money: 11 }))).toBe(true);
  });

  it('isEligibleForSession требует active, совпадение ревизии и отсутствие diff', () => {
    const sheet = version();
    expect(
      sessionCharacterService.isEligibleForSession({
        membershipStatus: 'active',
        approved: sheet,
        actual: sheet,
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(true);
    expect(
      sessionCharacterService.isEligibleForSession({
        membershipStatus: 'submitted',
        approved: sheet,
        actual: sheet,
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
    expect(
      sessionCharacterService.isEligibleForSession({
        membershipStatus: 'active',
        approved: sheet,
        actual: version({ rulesRevision: 6 }),
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
  });
});
