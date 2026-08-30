import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';

export function submittedRulesRevision(
  membership: Pick<GameCharacterMembership, 'approvedCharacterVersion'>,
  actual: CharacterVersion | null,
): number | null {
  return actual?.rulesRevision ?? membership.approvedCharacterVersion?.rulesRevision ?? null;
}

export function membershipMatchesGameRevision(actual: CharacterVersion | null, gameRulesRevision: number): boolean {
  if (actual === null) return false;

  return actual.rulesRevision === gameRulesRevision;
}
