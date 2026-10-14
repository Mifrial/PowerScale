import { describe, expect, it } from 'vitest';
import {
  membershipMatchesGameRevision,
  needsCharacterMigration,
} from '@/modules/Roleplay/Game/Utils/membershipRevision';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';

const version = (rulesRevision: number) => ({ rulesRevision }) as CharacterVersion;

const membership = (
  patch: Partial<Pick<GameCharacterMembership, 'membershipStatus' | 'approvedCharacterVersion'>>,
): Pick<GameCharacterMembership, 'membershipStatus' | 'approvedCharacterVersion'> => ({
  membershipStatus: 'active',
  approvedCharacterVersion: version(2),
  ...patch,
});

describe('membershipMatchesGameRevision', () => {
  it('actual на ревизии игры — можно модерировать', () => {
    expect(membershipMatchesGameRevision(version(12), 12)).toBe(true);
  });

  it('лист другой ревизии — нельзя', () => {
    expect(membershipMatchesGameRevision(version(6), 12)).toBe(false);
  });

  it('нет actual — нельзя', () => {
    expect(membershipMatchesGameRevision(null, 12)).toBe(false);
  });
});

describe('needsCharacterMigration', () => {
  it('без runtime берёт ревизию approve — чип виден в PHP-списке', () => {
    expect(needsCharacterMigration(membership({}), 3)).toBe(true);
  });

  it('approve совпадает с игрой — перехода нет', () => {
    expect(needsCharacterMigration(membership({ approvedCharacterVersion: version(3) }), 3)).toBe(false);
  });

  it('left не переводят', () => {
    expect(needsCharacterMigration(membership({ membershipStatus: 'left' }), 3)).toBe(false);
  });

  it('нет ни known, ни approve — не угадываем', () => {
    expect(needsCharacterMigration(membership({ approvedCharacterVersion: null }), 3)).toBe(false);
  });

  it('knownRevision перекрывает approve', () => {
    expect(needsCharacterMigration(membership({ approvedCharacterVersion: version(2) }), 3, 3)).toBe(false);
    expect(needsCharacterMigration(membership({ approvedCharacterVersion: version(3) }), 3, 2)).toBe(true);
  });
});
