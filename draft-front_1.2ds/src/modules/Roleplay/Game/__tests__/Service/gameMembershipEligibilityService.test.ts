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

  it('sheetNeedsModeration совпадает с needsModeration и не смотрит identity', () => {
    const sheet = version();
    expect(gameMembershipEligibilityService.sheetNeedsModeration(sheet, sheet)).toBe(false);
    expect(gameMembershipEligibilityService.sheetNeedsModeration(sheet, version({ money: 11 }))).toBe(true);
    expect(gameMembershipEligibilityService.sheetNeedsModeration(null, sheet)).toBe(true);
    expect(
      gameMembershipEligibilityService.sheetNeedsModeration(
        version({ spaceCode: 'old', rulesRevision: 1 }),
        version({ spaceCode: 'new', rulesRevision: 2 }),
      ),
    ).toBe(false);
  });

  it('canSpeakAsCharacter требует своего active и лист без модерации', () => {
    const sheet = version();
    expect(
      gameMembershipEligibilityService.canSpeakAsCharacter({
        membershipStatus: 'active',
        characterOwnerId: 4,
        currentUserId: 4,
        approved: sheet,
        actual: sheet,
      }),
    ).toBe(true);
    expect(
      gameMembershipEligibilityService.canSpeakAsCharacter({
        membershipStatus: 'active',
        characterOwnerId: 4,
        currentUserId: 9,
        approved: sheet,
        actual: sheet,
      }),
    ).toBe(false);
    expect(
      gameMembershipEligibilityService.canSpeakAsCharacter({
        membershipStatus: 'active',
        characterOwnerId: 4,
        currentUserId: 4,
        approved: sheet,
        actual: version({ money: 11 }),
      }),
    ).toBe(false);
    expect(
      gameMembershipEligibilityService.canSpeakAsCharacter({
        membershipStatus: 'submitted',
        characterOwnerId: 4,
        currentUserId: 4,
        approved: sheet,
        actual: sheet,
      }),
    ).toBe(false);
  });

  it('запущенная сессия не заменяет проверку листа участием в сессии', () => {
    const sheet = version();
    const inSession = gameMembershipEligibilityService.isActiveSessionParticipant({
      membershipStatus: 'active',
      sessionParticipant: true,
      returned: false,
    });

    expect(inSession).toBe(true);
    expect(gameMembershipEligibilityService.sheetNeedsModeration(sheet, version({ money: 11 }))).toBe(true);
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
