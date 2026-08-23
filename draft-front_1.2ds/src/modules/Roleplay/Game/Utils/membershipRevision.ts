import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';

/** Версия, которую смотрит модерация / миграция: pending, иначе latest, иначе active. */
export function submittedVersion(membership: GameCharacterMembership): CharacterVersion | null {
  return membership.pendingVersion ?? membership.latestVersion ?? membership.activeVersion;
}

export function submittedRulesRevision(membership: GameCharacterMembership): number | null {
  return submittedVersion(membership)?.rulesRevision ?? null;
}

export function membershipMatchesGameRevision(
  membership: Pick<GameCharacterMembership, 'pendingVersion' | 'latestVersion' | 'activeVersion'>,
  gameRulesRevision: number,
): boolean {
  const revision = membership.pendingVersion?.rulesRevision ?? membership.latestVersion?.rulesRevision ?? null;
  if (revision === null) return false;

  return revision === gameRulesRevision;
}
