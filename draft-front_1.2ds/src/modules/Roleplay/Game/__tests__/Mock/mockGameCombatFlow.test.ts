import { describe, expect, it } from 'vitest';
import {
  submitCombatChanges,
  moderateCharacter,
  fetchGameCharacters,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  setCombatResource,
  addCombatState,
  combatKey,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { versions, fetchCharacter } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';

const charKey = combatKey('character', 1);

/** Остановка сессии игры 2 (игра 'playing' в фикстурах → 'in_process'). */
function stopSession(): void {
  const detail = gameDetails.find((d) => d.game.id === 2);
  if (detail) detail.game.status = 'in_process';
}

describe('mockGameMemberships: поток боевых изменений (CD-2, модель версий — Баг 1)', () => {
  it('submitCombatChanges без изменений оверлея не трогает approved-членство', async () => {
    // Персонаж 1 (игра 2) approved в фикстурах, оверлей пуст — pending не создаётся.
    await submitCombatChanges(2);
    const chars = await fetchGameCharacters(2);
    const membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('approved');
    expect(membership.pendingVersion).toBeNull();
  });

  it('во время активной сессии боевые правки живут в оверлее; после остановки — pending и approve', async () => {
    // Правка боя: ресурс и состояние персонажа 1 (игра 2 — играется).
    await setCombatResource(2, charKey, 'rule-18', { base: 1, size: 0 });
    await addCombatState(2, charKey, { stateRuleId: 'rule-63', value: 5 });
    // Standalone-правка карточки «вне сессии»: меняем деньги в latest (versions[1]).
    versions[1].money = 777;

    // Пока сессия активна: pending не создаётся, approved заморожен, игра читает approved + оверлей.
    await submitCombatChanges(2);
    let chars = await fetchGameCharacters(2);
    let membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('approved');
    expect(membership.pendingVersion).toBeNull();
    const effective = mergeCombatOverlay(membership.activeVersion!, membership.overlay!);
    expect(effective.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
    expect(effective.states).toContainEqual({ stateRuleId: 'rule-63', value: 5 });
    // Standalone-правка денег не проникает в игру до одобрения (approved заморожен).
    expect(effective.money).toBe(membership.activeVersion!.money);

    // Остановка сессии → pending = reconcile(active, latest, оверлей): деньги из latest + боевые правки.
    stopSession();
    await submitCombatChanges(2);
    chars = await fetchGameCharacters(2);
    membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('pending');
    expect(membership.pendingVersion?.money).toBe(777);
    expect(membership.pendingVersion?.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({
      base: 1,
      size: 0,
    });
    expect(membership.pendingVersion?.states).toContainEqual({ stateRuleId: 'rule-63', value: 5 });

    // Approve: активной становится собранная версия; оверлей очищен.
    await moderateCharacter(2, 1, 'approve');
    chars = await fetchGameCharacters(2);
    membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('approved');
    expect(membership.pendingVersion).toBeNull();
    const approved = membership.activeVersion!;
    expect(approved.money).toBe(777);
    expect(approved.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
    expect(approved.states).toContainEqual({ stateRuleId: 'rule-63', value: 5 });
    expect(getStoredCombatOverlay(2, charKey)).toBeNull();
  });

  it('reject отбрасывает оверлей, pending сбрасывается, версия не меняется', async () => {
    const before = versions[1].resources.find((r) => r.ruleId === 'rule-18')!.current;

    await setCombatResource(2, charKey, 'rule-18', { base: 2, size: 0 });
    await submitCombatChanges(2);
    await moderateCharacter(2, 1, 'reject');

    const chars = await fetchGameCharacters(2);
    const membership = chars.find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('rejected');
    expect(membership.pendingVersion).toBeNull();
    expect(getStoredCombatOverlay(2, charKey)).toBeNull();
    // Версия персонажа не изменилась (reject отбросил оверлей).
    const sheet = await fetchCharacter(1);
    expect(sheet.version.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual(before);
  });
});
