import { describe, expect, it, vi } from 'vitest';
import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameCombatCommand } from '@/modules/Roleplay/Game/Dto/GameCombatCommand';
import { GameCombatCommandAdapter } from '@/modules/Roleplay/Game/Service/GameCombatCommandAdapter';

const result: GameAuthoritativeCommandResult = {
  commandId: 'server-command',
  status: 'accepted',
  battleId: 'battle-1',
  process: {
    processId: 'process-1',
    offerId: 1,
    status: 'created',
    processStateVersion: 0,
  },
  effects: [],
  affectedEntities: [],
  conflict: null,
};

describe('GameCombatCommandAdapter', () => {
  it('sends the caller-owned commandId through Game API', async () => {
    const submitCombatCommand = vi.fn().mockResolvedValue(result);
    const adapter = new GameCombatCommandAdapter({ submitCombatCommand });
    const command: Extract<GameCombatCommand, { commandType: 'attackDecision' }> = {
      commandId: 'attack-command-1',
      commandType: 'attackDecision',
      gameId: 2,
      sessionId: 'session-1',
      battleId: 'battle-1',
      processId: null,
      offerId: null,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 0,
      actorKey: 'character:1',
      targetKey: 'npc:5',
      expectedEntityVersions: { 'character:1': 1, 'npc:5': 2 },
      action: {
        actionRuleCode: 'attack',
        itemRuleCode: 'fekhtovalnyy-mech',
        profileType: 'strike',
        actionPointCost: 1,
      },
    };

    await expect(adapter.submitAttack(command)).resolves.toEqual(result);

    expect(submitCombatCommand).toHaveBeenCalledTimes(1);
    expect(submitCombatCommand.mock.calls[0]?.[0]).toEqual(command);
  });

  it('preserves the same commandId when the caller retries', async () => {
    const submitCombatCommand = vi.fn().mockResolvedValue(result);
    const adapter = new GameCombatCommandAdapter({ submitCombatCommand });
    const command: Extract<GameCombatCommand, { commandType: 'attackDecision' }> = {
      commandId: 'retryable-attack-command',
      commandType: 'attackDecision',
      gameId: 2,
      sessionId: 'session-1',
      battleId: 'battle-1',
      processId: null,
      offerId: null,
      expectedSessionStateVersion: 1,
      expectedBattleStateVersion: 0,
      actorKey: 'character:1',
      targetKey: 'npc:5',
      expectedEntityVersions: { 'character:1': 1, 'npc:5': 2 },
      action: {
        actionRuleCode: 'attack',
        itemRuleCode: 'fekhtovalnyy-mech',
        profileType: 'strike',
        actionPointCost: 1,
      },
    };

    await adapter.submitAttack(command);
    await adapter.submitAttack(command);

    expect(submitCombatCommand).toHaveBeenNthCalledWith(1, command, undefined);
    expect(submitCombatCommand).toHaveBeenNthCalledWith(2, command, undefined);
  });

  it('exposes changed entity keys without loading projections', () => {
    const adapter = new GameCombatCommandAdapter({
      submitCombatCommand: vi.fn(),
    });
    const changed: GameAuthoritativeCommandResult = {
      ...result,
      affectedEntities: [
        { kind: 'character', id: 1, actualVersion: 2, changedSections: ['resources'] },
        { kind: 'npc', id: 5, actualVersion: 3, changedSections: ['states'] },
      ],
    };

    expect(adapter.changedEntityKeys(changed)).toEqual(['character:1', 'npc:5']);
  });
});
