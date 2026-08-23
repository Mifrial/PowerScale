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

// Снимки фикстур: тесты мутируют синглтоны-моки — beforeEach возвращает их в исходное состояние.
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

describe('mockCharacterUpdate: роутер версий (модель Баг 1)', () => {
  it('с явным gameId активной сессии пишет в оверлей, latest не трогает', async () => {
    // Торвин (игра 2 — играется) approved. updateCharacter с gameId=2 → оверлей.
    const before = versions[1].money;
    const version = { ...versions[1], money: before + 100 };

    const detail = await updateCharacter(1, { version, status: 'ready', gameId: 2 });

    expect(detail.version.money).toBe(before); // карточка (latest) не изменилась
    const stored = getStoredCombatOverlay(2, combatKey('character', 1));
    expect(stored?.sheet?.money).toBe(before + 100);

    const chars = await fetchGameCharacters(2);
    const membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('approved');
    expect(membership.pendingVersion).toBeNull();
    expect(membership.overlay?.sheet?.money).toBe(before + 100);
  });

  it('без gameId (standalone-карточка) пишет в latest с автоподачей', async () => {
    // Гаррик (игра 1, ревизия 6 vs 12): правка latest не ставит на модерацию — автоотклон.
    const before = versions[3].money;
    const detail = await updateCharacter(3, { version: { ...versions[3], money: before + 50 }, status: 'ready' });

    expect(detail.version.money).toBe(before + 50);
    const chars = await fetchGameCharacters(1);
    const membership = chars.find((m) => m.characterId === 3)!;
    expect(membership.membershipStatus).toBe('rejected');
    expect(membership.pendingVersion).toBeNull();
    expect(membership.activeVersion?.money).toBe(before); // approved заморожен
  });

  it('gameId без approved-членства в активной сессии игнорируется (fallback в latest)', async () => {
    const before = versions[1].money;
    const detail = await updateCharacter(1, {
      version: { ...versions[1], money: before + 1 },
      status: 'ready',
      gameId: 999,
    });

    expect(detail.version.money).toBe(before + 1);
    // Оверлей не создан (нет активной сессии для gameId 999); fallback → автоподача Торвина в pending.
    expect(getStoredCombatOverlay(999, combatKey('character', 1))).toBeNull();
    const chars = await fetchGameCharacters(2);
    expect(chars.find((m) => m.characterId === 1)?.membershipStatus).toBe('pending');
  });

  it('addCustomRule во время сессии → оверлей; вне сессии → latest с автоподачей', async () => {
    // Игра 2 играется: правило уходит в оверлей Торвина.
    const detail = await addCustomRule(1, { kind: 'item', name: 'Амулет', description: '' });
    expect(detail.version.customRules?.[0]).toBeUndefined();
    const stored = getStoredCombatOverlay(2, combatKey('character', 1));
    expect(stored?.sheet?.customRules?.[0]?.name).toBe('Амулет');

    // Игра 1 не играется: правило идёт в latest Гаррика + автоподача.
    const detail2 = await addCustomRule(3, { kind: 'item', name: 'Лаваш', description: '' });
    expect(detail2.version.customRules?.[0]?.name).toBe('Лаваш');
    const chars = await fetchGameCharacters(1);
    const garrick = chars.find((m) => m.characterId === 3)!;
    expect(garrick.membershipStatus).toBe('rejected');
  });

  it('updateCustomRule во время сессии правит запись в оверлее (latest не тронут)', async () => {
    await addCustomRule(1, { kind: 'item', name: 'Амулет', description: '' });
    const stored = getStoredCombatOverlay(2, combatKey('character', 1));
    const entryId = stored!.sheet!.customRules![0].id;

    await updateCustomRule(1, entryId, { status: 'deprecated' });

    const updated = getStoredCombatOverlay(2, combatKey('character', 1))?.sheet?.customRules?.[0];
    expect(updated?.status).toBe('deprecated');
    expect(versions[1].customRules?.[0]).toBeUndefined();
  });
});

describe('mockCharacterUpdate: полный цикл in-game редактора → approve', () => {
  it('правки из игры проходят через оверлей в approved после остановки сессии и approve', async () => {
    const before = versions[1].money;
    await updateCharacter(1, { version: { ...versions[1], money: before + 100 }, status: 'ready', gameId: 2 });
    expect(versions[1].money).toBe(before);

    // Остановка сессии → pending из оверлея; approve → latest + approved.
    const detail = gameDetails.find((d) => d.game.id === 2);
    if (detail) detail.game.status = 'in_process';
    await submitCombatChanges(2);
    let chars = await fetchGameCharacters(2);
    expect(chars.find((m) => m.characterId === 1)?.membershipStatus).toBe('pending');

    await moderateCharacter(2, 1, 'approve');
    chars = await fetchGameCharacters(2);
    const membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('approved');
    expect(membership.activeVersion?.money).toBe(before + 100);
    const sheet = await fetchCharacter(1);
    expect(sheet.version.money).toBe(before + 100);
  });
});

describe('mockCharacterUpdate: утилиты', () => {
  it('applyVersionChange перепривязывает лист и автоподаёт членства', async () => {
    const before = versions[3].money;
    versions[3].money = before + 10;
    applyVersionChange(3);
    const detail = await fetchCharacter(3);
    expect(detail.version.money).toBe(before + 10);
    const chars = await fetchGameCharacters(1);
    expect(chars.find((m) => m.characterId === 3)?.membershipStatus).toBe('rejected');
  });
});
