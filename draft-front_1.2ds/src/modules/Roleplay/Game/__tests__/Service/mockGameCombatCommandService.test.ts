import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import type { GameCombatCommand } from '@/modules/Roleplay/Game/Dto/GameCombatCommand';
import {
  captureCharacterRuntimeState,
  getCharacterActualVersion,
  getStoredCharacterVersion,
  restoreCharacterRuntimeState,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import {
  captureNpcRuntimeState,
  fetchNpcs,
  restoreNpcRuntimeState,
  updateNpc,
} from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { versions } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import {
  gameCharacterMemberships,
  fetchGameCharacters,
  moderateCharacter,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  clearMockGameState,
  configureMockGameState,
  endGameBattle,
  getGameStateSnapshot,
  restoreGameStateSnapshot,
  serializeGameStateSnapshot,
  startGameBattle,
  startGameSession,
  stopGameStateSession,
} from '@/modules/Roleplay/Game/Mock/mockGameState';
import { mockGameCombatCommandService } from '@/modules/Roleplay/Game/Service/Instance/mockGameCombatCommandService';
import { MockGameCombatCommandService } from '@/modules/Roleplay/Game/Service/MockGameCombatCommandService';
import { mockGameRealtimePort } from '@/modules/Roleplay/Game/Mock/mockGameRealtimePort';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const gameId = 2;
const characterKey = 'character:1' as const;
const npcKey = 'npc:5' as const;

async function setupBattle(): Promise<{ sessionId: string; battleId: string }> {
  configureMockGameState();
  const session = await startGameSession({
    commandId: 'combat-session',
    commandType: 'startSession',
    gameId,
    sessionId: null,
    battleId: null,
    participantEntityKeys: [characterKey, npcKey],
    payload: {},
  });
  if (session.kind !== 'transition' || !session.snapshot.session) throw new Error('Session was not created');

  const battle = await startGameBattle({
    commandId: 'combat-battle',
    commandType: 'startBattle',
    gameId,
    sessionId: session.snapshot.session.sessionId,
    battleId: null,
    expectedSessionStateVersion: session.snapshot.session.sessionStateVersion,
    payload: {},
  });
  if (battle.kind !== 'transition' || !battle.snapshot.battle) throw new Error('Battle was not created');

  return {
    sessionId: battle.snapshot.battle.sessionId,
    battleId: battle.snapshot.battle.battleId,
  };
}

function attackCommand(
  sessionId: string,
  battleId: string,
  commandId: string,
): Extract<GameCombatCommand, { commandType: 'attackDecision' }> {
  return {
    commandId,
    commandType: 'attackDecision',
    gameId,
    sessionId,
    battleId,
    processId: null,
    offerId: null,
    expectedSessionStateVersion: 1,
    expectedBattleStateVersion: 0,
    actorKey: characterKey,
    targetKey: npcKey,
    expectedEntityVersions: {
      [characterKey]: getCharacterActualVersion(1),
      [npcKey]: 2,
    },
    action: {
      actionRuleCode: 'attack',
      itemRuleCode: 'fekhtovalnyy-mech',
      profileType: 'strike',
      actionPointCost: 1,
    },
  };
}

describe('MockGameCombatCommandService', () => {
  const characterSnapshot = captureCharacterRuntimeState(1);
  const npcSnapshot = captureNpcRuntimeState(5);
  const membership = gameCharacterMemberships.find((entry) => entry.gameId === gameId && entry.characterId === 1);
  const membershipSnapshot = membership ? cloneData(membership) : null;

  beforeEach(async () => {
    await updateNpc(5, {
      expectedNpcActualVersion: (await fetchNpcs(gameId)).find((npc) => npc.id === 5)?.actualVersion ?? 0,
      name: 'Профессор Шторм',
      shortDescription: 'Доступный для combat fixture NPC',
      fullDescription: null,
      tags: ['npc'],
      visibility: [],
      version: cloneData(versions[1]),
    });
  });

  afterEach(() => {
    restoreCharacterRuntimeState(1, characterSnapshot);
    restoreNpcRuntimeState(5, npcSnapshot);
    if (membership && membershipSnapshot) Object.assign(membership, cloneData(membershipSnapshot));
    clearMockGameState();
  });

  it('creates a process without mutating actual before defense is applied', async () => {
    const { sessionId, battleId } = await setupBattle();
    const before = getStoredCharacterVersion(1);
    const result = await mockGameCombatCommandService.submit(attackCommand(sessionId, battleId, 'attack-1'));

    expect(result.status).toBe('accepted');
    expect(result.process.status).toBe('created');
    expect(result.effects).toEqual([]);
    expect(getStoredCharacterVersion(1)).toEqual(before);
  });

  it('replays a committed command when response delivery fails after commit', async () => {
    const { sessionId, battleId } = await setupBattle();
    let failResponse = true;
    const service = new MockGameCombatCommandService(undefined, undefined, undefined, () => {
      if (failResponse) {
        failResponse = false;
        throw new Error('response delivery failed');
      }
    });
    const command = attackCommand(sessionId, battleId, 'timeout-after-commit');

    await expect(service.submit(command)).rejects.toThrow('response delivery failed');
    const replay = await service.submit(command);

    expect(replay.status).toBe('accepted');
    expect(replay.commandId).toBe(command.commandId);
    expect(Object.keys((await getGameStateSnapshot(gameId)).battle?.combatProcesses ?? {})).toHaveLength(1);
  });

  it('applies resource and NPC state exactly once and replays duplicate commands', async () => {
    const { sessionId, battleId } = await setupBattle();
    const attack = await mockGameCombatCommandService.submit(attackCommand(sessionId, battleId, 'attack-1'));
    if (!attack.process.processId || attack.process.offerId === null) throw new Error('Attack process was not created');

    const characterVersion = getCharacterActualVersion(1);
    const npcVersion = 2;
    const defense: GameCombatCommand = {
      commandId: 'defense-1',
      commandType: 'defenseDecision',
      gameId,
      sessionId,
      battleId,
      processId: attack.process.processId,
      offerId: attack.process.offerId,
      expectedProcessStateVersion: attack.process.processStateVersion,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 1,
      actorKey: npcKey,
      targetKey: characterKey,
      expectedEntityVersions: {
        [characterKey]: characterVersion,
        [npcKey]: npcVersion,
      },
      defense: { reaction: 'ignore' },
    };
    const applied = await mockGameCombatCommandService.submit(defense);
    const replay = await mockGameCombatCommandService.submit(defense);

    expect(applied.status).toBe('applied');
    expect(applied.effects.map((effect) => effect.kind)).toEqual(['resourceSpend', 'damage', 'state']);
    expect(applied.affectedEntities).toHaveLength(2);
    expect(replay).toEqual(applied);
    expect(
      getStoredCharacterVersion(1).resources.find((resource) => resource.ruleCode === 'action-points')?.current.base,
    ).toBe(3);
    expect(captureNpcRuntimeState(5).version?.states.some((state) => state.stateRuleCode === 'wound')).toBe(true);
    expect((await fetchGameCharacters(gameId)).find((entry) => entry.characterId === 1)?.reviewState).toBe(
      'changes_pending',
    );
  });

  it('keeps combat command idempotency records across mock state restore', async () => {
    const { sessionId, battleId } = await setupBattle();
    const command = attackCommand(sessionId, battleId, 'attack-restore');
    const first = await mockGameCombatCommandService.submit(command);
    const serialized = serializeGameStateSnapshot(gameId);
    clearMockGameState();
    restoreGameStateSnapshot(JSON.parse(serialized));

    const replay = await mockGameCombatCommandService.submit(command);

    expect(replay).toEqual(first);
    expect(Object.keys((await getGameStateSnapshot(gameId)).battle?.combatProcesses ?? {})).toHaveLength(1);
  });

  it('rejects stale versions and reuses only an identical command fingerprint', async () => {
    const { sessionId, battleId } = await setupBattle();
    const command = attackCommand(sessionId, battleId, 'attack-conflict');
    const accepted = await mockGameCombatCommandService.submit(command);
    const replay = await mockGameCombatCommandService.submit(command);
    const conflict = await mockGameCombatCommandService.submit({
      ...command,
      action: { ...command.action, actionPointCost: 2 },
    });
    const staleSession = await mockGameCombatCommandService.submit({
      ...command,
      commandId: 'attack-session-stale',
      expectedSessionStateVersion: 0,
    });
    const staleDefense = await mockGameCombatCommandService.submit({
      commandId: 'defense-stale',
      commandType: 'defenseDecision',
      gameId,
      sessionId,
      battleId,
      processId: accepted.process.processId ?? '',
      offerId: accepted.process.offerId ?? 0,
      expectedProcessStateVersion: accepted.process.processStateVersion,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 1,
      actorKey: npcKey,
      targetKey: characterKey,
      expectedEntityVersions: { [characterKey]: 999, [npcKey]: 2 },
      defense: { reaction: 'ignore' },
    });

    expect(replay).toEqual(accepted);
    expect(conflict.conflict?.code).toBe('command_fingerprint_conflict');
    expect(staleSession.conflict?.currentSessionStateVersion).toBe(1);
    expect(staleDefense.conflict?.code).toBe('stale_version');
  });

  it('does not accept a returned participant for a new combat command', async () => {
    const { sessionId, battleId } = await setupBattle();
    if (!membership) throw new Error('Membership fixture was not found');
    await moderateCharacter(gameId, 1, 'returnForRework');

    const result = await mockGameCombatCommandService.submit(attackCommand(sessionId, battleId, 'attack-returned'));

    expect(result.conflict?.code).toBe('invalid_actor');
  });

  it('returns current session versions when retrying a command after endBattle', async () => {
    const { sessionId, battleId } = await setupBattle();
    const command = attackCommand(sessionId, battleId, 'attack-closed-battle');

    const accepted = await mockGameCombatCommandService.submit(command);
    if (!accepted.process.processId) throw new Error('Attack process was not created');
    const ended = await endGameBattle({
      commandId: 'end-closed-battle',
      commandType: 'endBattle',
      gameId,
      sessionId,
      battleId,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 1,
      payload: {},
    });

    expect(ended.kind).toBe('transition');
    if (ended.kind === 'transition') {
      expect(ended.transition.cleanup?.cancelledProcessIds).toContain(accepted.process.processId);
    }
    const retry = await mockGameCombatCommandService.submit(command);

    expect(retry.conflict?.code).toBe('closed_battle');
    expect(retry.conflict?.currentSessionStateVersion).toBe(2);
    expect(retry.conflict?.currentBattleStateVersion).toBeNull();
  });

  it('returns terminal versions when retrying a command after stopSession', async () => {
    const { sessionId, battleId } = await setupBattle();
    const command = attackCommand(sessionId, battleId, 'attack-closed-session');

    await expect(mockGameCombatCommandService.submit(command)).resolves.toMatchObject({ status: 'accepted' });
    const stopped = await stopGameStateSession({
      commandId: 'stop-closed-session',
      commandType: 'stopSession',
      gameId,
      sessionId,
      battleId: null,
      expectedSessionStateVersion: 1,
      payload: {},
    });

    expect(stopped.kind).toBe('transition');
    const retry = await mockGameCombatCommandService.submit(command);

    expect(retry.conflict?.code).toBe('closed_battle');
    expect(retry.conflict?.currentSessionStateVersion).toBe(1);
    expect(retry.conflict?.currentBattleStateVersion).toBe(1);
  });

  it('does not apply a pending process after its attacker is returned', async () => {
    const { sessionId, battleId } = await setupBattle();
    const attack = await mockGameCombatCommandService.submit(
      attackCommand(sessionId, battleId, 'attack-returned-pending'),
    );
    if (!attack.process.processId || attack.process.offerId === null) throw new Error('Attack process was not created');

    await moderateCharacter(gameId, 1, 'returnForRework');
    const result = await mockGameCombatCommandService.submit({
      commandId: 'defense-after-return',
      commandType: 'defenseDecision',
      gameId,
      sessionId,
      battleId,
      processId: attack.process.processId,
      offerId: attack.process.offerId,
      expectedProcessStateVersion: attack.process.processStateVersion,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 1,
      actorKey: npcKey,
      targetKey: characterKey,
      expectedEntityVersions: {
        [characterKey]: getCharacterActualVersion(1),
        [npcKey]: 2,
      },
      defense: { reaction: 'ignore' },
    });

    expect(result.conflict?.code).toBe('invalid_target');
  });

  it('does not create a process for an entity outside the active session', async () => {
    const { sessionId, battleId } = await setupBattle();
    const command = attackCommand(sessionId, battleId, 'attack-outsider');
    const result = await mockGameCombatCommandService.submit({
      ...command,
      targetKey: 'character:3',
      expectedEntityVersions: {
        [characterKey]: getCharacterActualVersion(1),
        'character:3': getCharacterActualVersion(3),
      },
    });

    expect(result.conflict?.code).toBe('invalid_target');
    expect(Object.keys((await getGameStateSnapshot(gameId)).battle?.combatProcesses ?? {})).toHaveLength(0);
  });

  it('rolls back entity and process state after a pre-commit exception', async () => {
    const { sessionId, battleId } = await setupBattle();
    const attack = await mockGameCombatCommandService.submit(attackCommand(sessionId, battleId, 'attack-rollback'));
    if (!attack.process.processId || attack.process.offerId === null) throw new Error('Attack process was not created');

    const characterActualVersion = getCharacterActualVersion(1);
    const defense: GameCombatCommand = {
      commandId: 'defense-rollback',
      commandType: 'defenseDecision',
      gameId,
      sessionId,
      battleId,
      processId: attack.process.processId,
      offerId: attack.process.offerId,
      expectedProcessStateVersion: attack.process.processStateVersion,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 1,
      actorKey: npcKey,
      targetKey: characterKey,
      expectedEntityVersions: { [characterKey]: characterActualVersion, [npcKey]: 2 },
      defense: { reaction: 'ignore' },
    };
    const failingService = new MockGameCombatCommandService(() => {
      throw new Error('fixture pre-commit failure');
    });

    await expect(failingService.submit(defense)).rejects.toThrow('fixture pre-commit failure');
    expect(getCharacterActualVersion(1)).toBe(characterActualVersion);
    expect(Object.keys((await getGameStateSnapshot(gameId)).battle?.combatProcesses ?? {})).toHaveLength(1);
    await expect(mockGameCombatCommandService.submit(defense)).resolves.toMatchObject({ status: 'applied' });
  });

  it('does not publish affected-entity events for a rolled-back transition', async () => {
    const { sessionId, battleId } = await setupBattle();
    const attack = await mockGameCombatCommandService.submit(attackCommand(sessionId, battleId, 'event-rollback'));
    if (!attack.process.processId || attack.process.offerId === null) throw new Error('Attack process was not created');

    const events: string[] = [];
    const stop = mockGameRealtimePort.subscribe(gameId, (event) => events.push(event.eventId));
    try {
      const defense: GameCombatCommand = {
        commandId: 'event-rollback-defense',
        commandType: 'defenseDecision',
        gameId,
        sessionId,
        battleId,
        processId: attack.process.processId,
        offerId: attack.process.offerId,
        expectedProcessStateVersion: attack.process.processStateVersion,
        expectedSessionStateVersion: 1,
        expectedBattleStateVersion: 1,
        actorKey: npcKey,
        targetKey: characterKey,
        expectedEntityVersions: {
          [characterKey]: getCharacterActualVersion(1),
          [npcKey]: 2,
        },
        defense: { reaction: 'ignore' },
      };
      const failingService = new MockGameCombatCommandService(() => {
        throw new Error('fixture event rollback');
      });

      await expect(failingService.submit(defense)).rejects.toThrow('fixture event rollback');
      expect(events).toEqual([]);
    } finally {
      stop();
    }
  });
});
