import { beforeEach, describe, expect, it } from 'vitest';
import {
  updateCharacter,
  addCustomRule,
  updateCustomRule,
  applyVersionChange,
} from '@/modules/Roleplay/Character/Mock/mockCharacterUpdate';
import { versions, fetchCharacter, syncCharacterVersion } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import {
  gameCharacterMemberships,
  fetchGameCharacters,
  moderateCharacter,
  submitCombatChanges,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  getStoredCombatOverlay,
  clearCombatOverlay,
  combatKey,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import '@/modules/Roleplay/Game/Mock/mockCharacterSessionOverlay';

const initialVersion1 = JSON.parse(JSON.stringify(versions[1])) as CharacterVersion;
const initialVersion3 = JSON.parse(JSON.stringify(versions[3])) as CharacterVersion;
const initialTorvin = JSON.parse(
  JSON.stringify(gameCharacterMemberships.find((m) => m.gameId === 2 && m.characterId === 1)!),
) as GameCharacterMembership;
const initialGarrick = JSON.parse(
  JSON.stringify(gameCharacterMemberships.find((m) => m.gameId === 1 && m.characterId === 3)!),
) as GameCharacterMembership;

function restoreMembership<T extends object>(membership: T, snapshot: GameCharacterMembership): void {
  Object.assign(membership, JSON.parse(JSON.stringify(snapshot)));
}

beforeEach(() => {
  versions[1] = JSON.parse(JSON.stringify(initialVersion1)) as CharacterVersion;
  versions[3] = JSON.parse(JSON.stringify(initialVersion3)) as CharacterVersion;
  restoreMembership(
    gameCharacterMemberships.find((m) => m.gameId === 2 && m.characterId === 1)!,
    initialTorvin,
  );
  restoreMembership(
    gameCharacterMemberships.find((m) => m.gameId === 1 && m.characterId === 3)!,
    initialGarrick,
  );
  const detail = gameDetails.find((d) => d.game.id === 2);
  if (detail) detail.game.status = 'playing';
  clearCombatOverlay(2, combatKey('character', 1));
  clearCombatOverlay(1, combatKey('character', 3));
  syncCharacterVersion(1);
  syncCharacterVersion(3);
});

describe('mockCharacterUpdate: роутер версий (DEC-059)', () => {
  it('с явным gameId активной сессии пишет в оверлей, actual не трогает', async () => {
    const before = versions[1].money;
    const version = { ...versions[1], money: before + 100 };

    const detail = await updateCharacter(1, { version, status: 'ready', gameId: 2 });

    expect(detail.version.money).toBe(before);
    const stored = getStoredCombatOverlay(2, combatKey('character', 1));
    expect(stored?.sheet?.money).toBe(before + 100);

    const membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    expect(membership.overlay?.sheet?.money).toBe(before + 100);
  });

  it('без gameId во время сессии запрещает save actual', async () => {
    await expect(
      updateCharacter(1, { version: { ...versions[1], money: versions[1].money + 1 }, status: 'ready' }),
    ).rejects.toThrow('сессии');
  });

  it('без gameId вне сессии пишет actual; approved заморожен', async () => {
    const before = versions[3].money;
    const detail = await updateCharacter(3, { version: { ...versions[3], money: before + 50 }, status: 'ready' });

    expect(detail.version.money).toBe(before + 50);
    const membership = (await fetchGameCharacters(1)).find((m) => m.characterId === 3)!;
    expect(membership.membershipStatus).toBe('active');
    expect(membership.reviewState).toBe('changes_pending');
    expect(membership.approvedCharacterVersion?.money).toBe(before);
  });

  it('чужой gameId во время сессии не пишет actual', async () => {
    await expect(
      updateCharacter(1, {
        version: { ...versions[1], money: versions[1].money + 1 },
        status: 'ready',
        gameId: 999,
      }),
    ).rejects.toThrow('сессии');
  });

  it('addCustomRule во время сессии → оверлей; вне сессии → actual', async () => {
    const detail = await addCustomRule(1, { kind: 'item', name: 'Амулет', description: '' });
    expect(detail.version.customRules?.[0]).toBeUndefined();
    expect(getStoredCombatOverlay(2, combatKey('character', 1))?.sheet?.customRules?.[0]?.name).toBe('Амулет');

    const detail2 = await addCustomRule(3, { kind: 'item', name: 'Лаваш', description: '' });
    expect(detail2.version.customRules?.[0]?.name).toBe('Лаваш');
    const garrick = (await fetchGameCharacters(1)).find((m) => m.characterId === 3)!;
    expect(garrick.membershipStatus).toBe('active');
    expect(garrick.reviewState).toBe('changes_pending');
  });

  it('updateCustomRule во время сессии правит запись в оверлее', async () => {
    await addCustomRule(1, { kind: 'item', name: 'Амулет', description: '' });
    const stored = getStoredCombatOverlay(2, combatKey('character', 1));
    const entryId = stored!.sheet!.customRules![0].id;

    await updateCustomRule(1, entryId, { status: 'deprecated' });

    const updated = getStoredCombatOverlay(2, combatKey('character', 1))?.sheet?.customRules?.[0];
    expect(updated?.status).toBe('deprecated');
    expect(versions[1].customRules?.[0]).toBeUndefined();
  });
});

describe('mockCharacterUpdate: полный цикл in-game редактора → commit', () => {
  it('правки из игры проходят через оверлей в actual после остановки сессии', async () => {
    const before = versions[1].money;
    await updateCharacter(1, { version: { ...versions[1], money: before + 100 }, status: 'ready', gameId: 2 });
    expect(versions[1].money).toBe(before);

    const detail = gameDetails.find((d) => d.game.id === 2);
    if (detail) detail.game.status = 'in_process';
    await submitCombatChanges(2);
    expect(versions[1].money).toBe(before + 100);

    await moderateCharacter(2, 1, 'approve');
    const membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.approvedCharacterVersion?.money).toBe(before + 100);
    const sheet = await fetchCharacter(1);
    expect(sheet.version.money).toBe(before + 100);
  });
});

describe('mockCharacterUpdate: утилиты', () => {
  it('applyVersionChange перепривязывает лист', async () => {
    const before = versions[3].money;
    versions[3].money = before + 10;
    applyVersionChange(3);
    const detail = await fetchCharacter(3);
    expect(detail.version.money).toBe(before + 10);
    const chars = await fetchGameCharacters(1);
    expect(chars.find((m) => m.characterId === 3)?.reviewState).toBe('changes_pending');
  });
});
