import { describe, expect, it } from 'vitest';
import { gameMembershipEligibilityService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipEligibilityService';
import { gameMembershipReviewService } from '@/modules/Roleplay/Game/Service/Instance/gameMembershipReviewService';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const version = (partial: Partial<CharacterVersion> = {}): CharacterVersion =>
  ({
    name: 'Герой',
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'actual',
    rulesRevision: 12,
    raceRuleCode: null,
    characteristics: [],
    resources: [{ ruleCode: 'action-points', current: { base: 3, size: 0 }, base: { base: 3, size: 0 }, bonuses: [] }],
    abilities: [],
    points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
    money: 10,
    ageYears: null,
    inventory: [],
    states: [],
    senses: [],
    ...partial,
  }) as CharacterVersion;

describe('GameMembershipEligibilityService', () => {
  it('needsModeration при пустом approved и при diff', () => {
    expect(gameMembershipReviewService.needsModeration(null, version())).toBe(true);
    expect(gameMembershipReviewService.needsModeration(version(), version())).toBe(false);
    expect(gameMembershipReviewService.needsModeration(version(), version({ money: 11 }))).toBe(true);
  });

  it('canStartSession требует active, identity игры и отсутствие diff', () => {
    const sheet = version();
    expect(
      gameMembershipEligibilityService.canStartSession({
        membershipStatus: 'active',
        returned: false,
        approved: sheet,
        actual: sheet,
        gameSpaceCode: 'actual',
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(true);
    expect(
      gameMembershipEligibilityService.canStartSession({
        membershipStatus: 'submitted',
        returned: false,
        approved: sheet,
        actual: sheet,
        gameSpaceCode: 'actual',
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
    expect(
      gameMembershipEligibilityService.canStartSession({
        membershipStatus: 'active',
        returned: false,
        approved: sheet,
        actual: version({ rulesRevision: 6 }),
        gameSpaceCode: 'actual',
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
  });

  it('canStartSession блокирует returned, changes_pending и несовместимый space', () => {
    const sheet = version();

    expect(
      gameMembershipEligibilityService.canStartSession({
        membershipStatus: 'active',
        returned: true,
        approved: sheet,
        actual: sheet,
        gameSpaceCode: 'actual',
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
    expect(
      gameMembershipEligibilityService.canStartSession({
        membershipStatus: 'active',
        returned: false,
        approved: sheet,
        actual: version({ money: 11 }),
        gameSpaceCode: 'actual',
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
    expect(
      gameMembershipEligibilityService.canStartSession({
        membershipStatus: 'active',
        returned: false,
        approved: sheet,
        actual: sheet,
        gameSpaceCode: 'other',
        gameRulesRevision: 12,
        needsFix: false,
      }),
    ).toBe(false);
  });

  it('active participant остаётся доступным после legal combat diff', () => {
    expect(
      gameMembershipEligibilityService.isActiveSessionParticipant({
        membershipStatus: 'active',
        sessionParticipant: true,
        returned: false,
      }),
    ).toBe(true);
  });

  it('isActiveSessionParticipant не выводит участие из membershipStatus и блокирует returned', () => {
    expect(
      gameMembershipEligibilityService.isActiveSessionParticipant({
        membershipStatus: 'active',
        sessionParticipant: false,
        returned: false,
      }),
    ).toBe(false);
    expect(
      gameMembershipEligibilityService.isActiveSessionParticipant({
        membershipStatus: 'active',
        sessionParticipant: true,
        returned: true,
      }),
    ).toBe(false);
  });
});
