import { describe, expect, it } from 'vitest';
import { membershipMatchesGameRevision } from '@/modules/Roleplay/Game/Utils/membershipRevision';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const version = (rulesRevision: number) => ({ rulesRevision }) as CharacterVersion;

describe('membershipMatchesGameRevision', () => {
  it('pending на ревизии игры — можно модерировать', () => {
    expect(
      membershipMatchesGameRevision(
        { pendingVersion: version(12), latestVersion: version(6), activeVersion: version(6) },
        12,
      ),
    ).toBe(true);
  });

  it('лист другой ревизии — нельзя', () => {
    expect(
      membershipMatchesGameRevision({ pendingVersion: null, latestVersion: version(6), activeVersion: version(6) }, 12),
    ).toBe(false);
  });
});
