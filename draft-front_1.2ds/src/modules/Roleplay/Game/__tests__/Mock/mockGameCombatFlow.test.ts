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
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';

const charKey = combatKey('character', 1);

function stopSession(): void {
  const detail = gameDetails.find((d) => d.game.id === 2);
  if (detail) detail.game.status = 'in_process';
}

describe('mockGameMemberships: поток боевых изменений (DEC-059)', () => {
  it('submitCombatChanges во время playing не пишет actual', async () => {
    const before = versions[1].money;
    await submitCombatChanges(2);
    const membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    expect(versions[1].money).toBe(before);
  });

  it('боевые правки живут в оверлее; после stop — в actual', async () => {
    await setCombatResource(2, charKey, 'rule-18', { base: 1, size: 0 });
    await addCombatState(2, charKey, { stateRuleId: 'rule-63', value: 5 });

    let membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    const effective = sessionCharacterService.resolve(membership.approvedCharacterVersion, membership.overlay);
    expect(effective?.resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
    expect(effective?.states).toContainEqual({ stateRuleId: 'rule-63', value: 5 });

    stopSession();
    await submitCombatChanges(2);
    membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    expect(versions[1].resources.find((r) => r.ruleId === 'rule-18')?.current).toEqual({ base: 1, size: 0 });
    expect(versions[1].states).toContainEqual({ stateRuleId: 'rule-63', value: 5 });
    expect(membership.reviewState).toBe('changes_pending');

    await moderateCharacter(2, 1, 'approve');
    membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.reviewState).toBe('clean');
    expect(getStoredCombatOverlay(2, charKey)).toBeNull();
  });
});
