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

/**
 * Лист другой ревизии, чем игра. `knownRevision` — actual из runtime/карточки/списка персонажей;
 * без него берётся снимок approve (PHP-список не гидрирует runtime).
 */
export function needsCharacterMigration(
  membership: Pick<GameCharacterMembership, 'membershipStatus' | 'approvedCharacterVersion'>,
  gameRulesRevision: number,
  knownRevision: number | null = null,
): boolean {
  if (membership.membershipStatus === 'left') return false;
  const revision = knownRevision ?? membership.approvedCharacterVersion?.rulesRevision ?? null;
  if (revision === null) return false;

  return revision !== gameRulesRevision;
}
