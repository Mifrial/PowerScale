import { describe, expect, it } from 'vitest';
import { fetchGameCharacters, moderateCharacter } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  setCombatResource,
  addCombatState,
  combatKey,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import {
  getCharacterActualVersion,
  getStoredCharacterVersion,
  versions,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails, stopGameSession } from '@/modules/Roleplay/Game/Mock/mockGames';
import { characterPatchService } from '@/modules/Roleplay/Character/init';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { mockGameRuntimeMutationService } from '@/modules/Roleplay/Game/Service/Instance/mockGameRuntimeMutationService';

const charKey = combatKey('character', 1);

describe('mockGameMemberships: поток боевых изменений (DEC-059)', () => {
  it('stopGameSession на non-playing бросает', async () => {
    const before = versions[1].money;
    const detail = gameDetails.find((d) => d.game.id === 2);
    if (detail) detail.game.status = 'in_process';
    await expect(stopGameSession(2, 'in_process')).rejects.toThrow('Сессия не активна');
    expect(versions[1].money).toBe(before);
    if (detail) detail.game.status = 'playing';
  });

  it('боевые правки сразу живут в actual и переживают stop', async () => {
    const detail = gameDetails.find((d) => d.game.id === 2);
    if (detail) detail.game.status = 'playing';
    await setCombatResource(2, charKey, 'action-points', { base: 1, size: 0 });
    await addCombatState(2, charKey, { stateRuleCode: 'stunned', value: 5 });

    expect(versions[1].resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 1, size: 0 });
    expect(versions[1].states).toContainEqual({ stateRuleCode: 'stunned', value: 5 });

    await stopGameSession(2, 'in_process');
    let membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.membershipStatus).toBe('active');
    expect(versions[1].resources.find((r) => r.ruleCode === 'action-points')?.current).toEqual({ base: 1, size: 0 });
    expect(versions[1].states).toContainEqual({ stateRuleCode: 'stunned', value: 5 });
    expect(membership.reviewState).toBe('changes_pending');

    await moderateCharacter(2, 1, 'approve');
    membership = (await fetchGameCharacters(2)).find((m) => m.characterId === 1)!;
    expect(membership.reviewState).toBe('clean');
    expect(getStoredCombatOverlay(2, charKey)).toBeNull();
  });

  it('typed runtime command поддерживает CAS и idempotent replay', () => {
    const before = getStoredCharacterVersion(1);
    const expectedActualVersion = getCharacterActualVersion(1);
    const commandId = createRandomId();
    const command = {
      commandId,
      gameId: 2,
      entityKey: charKey,
      patch: characterPatchService.createPatch(
        before,
        { ...before, money: before.money + 1 },
        commandId,
        expectedActualVersion,
      ),
    };

    const first = mockGameRuntimeMutationService.apply(command);
    const replay = mockGameRuntimeMutationService.apply(command);

    expect(replay).toEqual(first);
    expect(getStoredCharacterVersion(1).money).toBe(before.money + 1);
    expect(() =>
      mockGameRuntimeMutationService.apply({
        ...command,
        commandId: createRandomId(),
      }),
    ).toThrow('Актуальное состояние персонажа уже изменилось');
  });
});
