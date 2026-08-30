import { describe, expect, it } from 'vitest';
import { membershipMatchesGameRevision } from '@/modules/Roleplay/Game/Utils/membershipRevision';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const version = (rulesRevision: number) => ({ rulesRevision }) as CharacterVersion;

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
