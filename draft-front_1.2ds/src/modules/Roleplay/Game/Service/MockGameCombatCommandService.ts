import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameCombatCommand } from '@/modules/Roleplay/Game/Dto/GameCombatCommand';
import type { GameCombatCommandRecord } from '@/modules/Roleplay/Game/Dto/GameCombatCommandRecord';
import type { GameCombatConflict } from '@/modules/Roleplay/Game/Dto/GameCombatConflict';
import type { GameCombatEffect } from '@/modules/Roleplay/Game/Dto/GameCombatEffect';
import type { GameCombatProcessState } from '@/modules/Roleplay/Game/Dto/GameCombatProcessState';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { hasActiveGameSession } from '@/modules/Roleplay/Game/Mock/mockGameState';
import {
  captureMembershipRuntimeToken,
  isActiveSessionParticipant,
  markCharacterRuntimeMutation,
  restoreMembershipRuntimeToken,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import {
  getMockCombatCommandRecord,
  getMockGameStateVersions,
  isMockCommandClosed,
  rememberMockCombatCommandRecord,
  withMockGameBattleState,
} from '@/modules/Roleplay/Game/Mock/mockGameState';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import type { IGameCharacterRuntimeMutationPort } from '@/modules/Roleplay/Game/Interface/IGameCharacterRuntimeMutationPort';
import type { IGameNpcRuntimeMutationPort } from '@/modules/Roleplay/Game/Interface/IGameNpcRuntimeMutationPort';
import { MockGameCharacterRuntimeMutationPort } from '@/modules/Roleplay/Game/Mock/MockGameCharacterRuntimeMutationPort';
import { MockGameNpcRuntimeMutationPort } from '@/modules/Roleplay/Game/Mock/MockGameNpcRuntimeMutationPort';
import { mockGameRealtimePort } from '@/modules/Roleplay/Game/Mock/mockGameRealtimePort';
import { characterChangePort } from '@/modules/Roleplay/Character/init';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';

/**
 * Выполняет узкий authoritative combat flow в mock Game boundary.
 * Решения создают process/offer, а actual меняется только при applied transition.
 */
export class MockGameCombatCommandService {
  constructor(
    private readonly afterAttackerMutation: () => void = () => undefined,
    private readonly characterMutationPort: IGameCharacterRuntimeMutationPort = new MockGameCharacterRuntimeMutationPort(),
    private readonly npcMutationPort: IGameNpcRuntimeMutationPort = new MockGameNpcRuntimeMutationPort(),
    private readonly afterCommitResponseDelivery: (result: GameAuthoritativeCommandResult) => void = () => undefined,
  ) {}

  async submit(command: GameCombatCommand): Promise<GameAuthoritativeCommandResult> {
    if (isMockCommandClosed(command.gameId, command.commandId)) {
      const versions = getMockGameStateVersions(command.gameId);

      return this.conflictResult(
        command,
        null,
        'closed_battle',
        {},
        versions.sessionStateVersion,
        versions.battleStateVersion,
      );
    }

    try {
      const result = await withMockGameBattleState(command.gameId, async ({ session, battle }) => {
        const fingerprint = this.fingerprintOf(command);
        const previous = getMockCombatCommandRecord(command.gameId, command.commandId);
        if (previous) {
          if (previous.fingerprint !== fingerprint) {
            return this.conflictResult(
              command,
              battle,
              'command_fingerprint_conflict',
              {},
              session.sessionStateVersion,
            );
          }

          return cloneData(previous.result);
        }

        if (!hasActiveGameSession(command.gameId)) {
          return this.conflictResult(command, battle, 'game_not_playing', {}, session.sessionStateVersion);
        }
        if (session.sessionId !== command.sessionId) {
          return this.conflictResult(command, battle, 'closed_session', {}, session.sessionStateVersion);
        }
        if (battle.battleId !== command.battleId) {
          return this.conflictResult(command, battle, 'closed_battle', {}, session.sessionStateVersion);
        }
        if (
          !this.readEntity(command.actorKey) ||
          !this.isActiveActor(command.actorKey, command.gameId, session.participantEntityKeys)
        ) {
          return this.conflictResult(command, battle, 'invalid_actor', {}, session.sessionStateVersion);
        }
        if (
          !this.readEntity(command.targetKey) ||
          !this.isActiveActor(command.targetKey, command.gameId, session.participantEntityKeys)
        ) {
          return this.conflictResult(command, battle, 'invalid_target', {}, session.sessionStateVersion);
        }
        if (
          session.sessionStateVersion !== command.expectedSessionStateVersion ||
          battle.battleStateVersion !== command.expectedBattleStateVersion
        ) {
          return this.conflictResult(command, battle, 'stale_version', {}, session.sessionStateVersion);
        }

        const actor = this.readEntity(command.actorKey);
        if (!actor || !this.isActiveActor(command.actorKey, command.gameId, session.participantEntityKeys)) {
          return this.conflictResult(command, battle, 'invalid_actor', {}, session.sessionStateVersion);
        }

        if (command.commandType === 'attackDecision') {
          return this.acceptAttackDecision(
            command,
            battle,
            fingerprint,
            session.sessionStateVersion,
            session.participantEntityKeys,
          );
        }

        return this.applyDefenseDecision(
          command,
          battle,
          fingerprint,
          session.sessionStateVersion,
          session.participantEntityKeys,
        );
      });
      this.afterCommitResponseDelivery(result);

      return result;
    } catch (error) {
      if (error instanceof Error && error.message === 'No active Game battle') {
        return this.conflictResult(command, null, 'no_active_battle');
      }

      throw error;
    }
  }

  private acceptAttackDecision(
    command: Extract<GameCombatCommand, { commandType: 'attackDecision' }>,
    battle: {
      battleId: string;
      battleStateVersion: number;
      combatProcesses: Record<string, GameCombatProcessState>;
      checkOffers: { id: number }[];
      updatedAt: string;
    },
    fingerprint: string,
    sessionStateVersion: number,
    participantEntityKeys: readonly CombatEntityKey[],
  ): GameAuthoritativeCommandResult {
    if (command.actorKey === command.targetKey || command.action.actionPointCost <= 0) {
      return this.conflictResult(command, battle, 'invalid_command', {}, sessionStateVersion);
    }

    const target = this.readEntity(command.targetKey);
    if (!target || !this.isActiveActor(command.targetKey, command.gameId, participantEntityKeys)) {
      return this.conflictResult(command, battle, 'invalid_target', {}, sessionStateVersion);
    }
    const entityConflict = this.validateExpectedEntityVersions(command, [command.actorKey, command.targetKey]);
    if (entityConflict) {
      return this.conflictResult(command, battle, 'stale_version', entityConflict, sessionStateVersion);
    }

    const processId = createRandomId();
    const offerId = this.nextOfferId(battle);
    const now = new Date().toISOString();
    const process: GameCombatProcessState = {
      processId,
      actorKey: command.actorKey,
      targetKey: command.targetKey,
      action: cloneData(command.action),
      offerId,
      status: 'awaitingDefense',
      processStateVersion: 0,
      createdAt: now,
      updatedAt: now,
    };
    battle.combatProcesses[processId] = process;
    battle.battleStateVersion += 1;
    battle.updatedAt = now;

    const result = this.resultOf(command, {
      processId,
      offerId,
      status: 'created',
      processStateVersion: process.processStateVersion,
    });
    this.remember(command, fingerprint, result);

    return result;
  }

  private applyDefenseDecision(
    command: Extract<GameCombatCommand, { commandType: 'defenseDecision' }>,
    battle: {
      battleId: string;
      battleStateVersion: number;
      combatProcesses: Record<string, GameCombatProcessState>;
      updatedAt: string;
    },
    fingerprint: string,
    sessionStateVersion: number,
    participantEntityKeys: readonly CombatEntityKey[],
  ): GameAuthoritativeCommandResult {
    const process = battle.combatProcesses[command.processId];
    if (!process || process.offerId !== command.offerId) {
      return this.conflictResult(command, battle, 'invalid_offer', {}, sessionStateVersion);
    }
    if (
      command.actorKey !== process.targetKey ||
      command.targetKey !== process.actorKey ||
      command.expectedProcessStateVersion !== process.processStateVersion
    ) {
      return this.conflictResult(command, battle, 'stale_version', {}, sessionStateVersion);
    }

    const attacker = this.readEntity(process.actorKey);
    const defender = this.readEntity(process.targetKey);
    if (!attacker || !defender) {
      return this.conflictResult(command, battle, 'invalid_target', {}, sessionStateVersion);
    }
    if (
      !this.isActiveActor(process.actorKey, command.gameId, participantEntityKeys) ||
      !this.isActiveActor(process.targetKey, command.gameId, participantEntityKeys)
    ) {
      return this.conflictResult(command, battle, 'invalid_target', {}, sessionStateVersion);
    }
    const entityConflict = this.validateExpectedEntityVersions(command, [process.actorKey, process.targetKey]);
    if (entityConflict) {
      return this.conflictResult(command, battle, 'stale_version', entityConflict, sessionStateVersion);
    }

    const actionPointResource = attacker.version.resources.find((resource) => resource.ruleCode === 'action-points');
    if (!actionPointResource || actionPointResource.current.base < process.action.actionPointCost) {
      return this.conflictResult(command, battle, 'invalid_command', {}, sessionStateVersion);
    }

    const battleSnapshot = cloneData(battle);
    const attackerSnapshot = this.captureEntity(process.actorKey);
    const defenderSnapshot = this.captureEntity(process.targetKey);
    const membershipTokens = new Map<number, { membershipRevision: number; updatedAt: string }>();
    for (const entityKey of [process.actorKey, process.targetKey]) {
      const [kind, rawId] = entityKey.split(':');
      if (kind === 'character')
        membershipTokens.set(Number(rawId), captureMembershipRuntimeToken(command.gameId, Number(rawId)));
    }
    const effects: GameCombatEffect[] = [];
    const affectedEntities: GameAuthoritativeCommandResult['affectedEntities'] = [];

    try {
      const remainingActionPoints = {
        ...attackDamageService.spendActionPoints(actionPointResource.current, process.action.actionPointCost),
      };
      const attackerPatch: CharacterPatch = {
        commandId: command.commandId,
        expectedActualVersion: attacker.actualVersion,
        operations: [
          {
            kind: 'setResourceCurrent',
            ruleCode: 'action-points',
            current: remainingActionPoints,
          },
        ],
      };
      this.replaceEntity(process.actorKey, attackerPatch);
      this.markMembershipMutation(command.gameId, process.actorKey);
      this.afterAttackerMutation();
      effects.push({
        effectId: createRandomId(),
        kind: 'resourceSpend',
        entityKey: process.actorKey,
        resourceRuleCode: 'action-points',
        amount: { base: process.action.actionPointCost, size: 0 },
        remaining: remainingActionPoints,
      });
      affectedEntities.push({
        kind: attacker.kind,
        id: attacker.id,
        actualVersion: attacker.actualVersion + 1,
        changedSections: ['resources'],
      });

      if (command.defense.reaction !== 'dodge') {
        const wound = { stateRuleCode: 'wound', value: 1 };
        const defenderPatch: CharacterPatch = {
          commandId: command.commandId,
          expectedActualVersion: defender.actualVersion,
          operations: [
            {
              kind: 'replaceSection',
              section: 'states',
              value: [...defender.version.states, wound],
            },
          ],
        };
        this.replaceEntity(process.targetKey, defenderPatch);
        this.markMembershipMutation(command.gameId, process.targetKey);
        effects.push({
          effectId: createRandomId(),
          kind: 'damage',
          entityKey: process.targetKey,
          damage: { base: 1, size: 0 },
          damageTypeCode: null,
        });
        effects.push({
          effectId: createRandomId(),
          kind: 'state',
          operation: 'add',
          entityKey: process.targetKey,
          stateRuleCode: wound.stateRuleCode,
          state: wound,
        });
        affectedEntities.push({
          kind: defender.kind,
          id: defender.id,
          actualVersion: defender.actualVersion + 1,
          changedSections: ['states'],
        });
      }

      delete battle.combatProcesses[process.processId];
      battle.battleStateVersion += 1;
      battle.updatedAt = new Date().toISOString();
      const result = this.resultOf(
        command,
        {
          processId: process.processId,
          offerId: process.offerId,
          status: 'applied',
          processStateVersion: process.processStateVersion + 1,
        },
        effects,
        affectedEntities,
      );
      this.remember(command, fingerprint, result);
      for (const affectedEntity of affectedEntities) {
        if (affectedEntity.kind === 'character') {
          characterChangePort.publish({
            characterId: affectedEntity.id,
            actualVersion: affectedEntity.actualVersion,
            changedSections: affectedEntity.changedSections,
            actorId: getCurrentUserId(),
            mutationKind: 'runtime_effect',
          });
        } else {
          mockGameRealtimePort.publishEntityChange({
            gameId: command.gameId,
            entityKey: `${affectedEntity.kind}:${affectedEntity.id}` as CombatEntityKey,
            actualVersion: affectedEntity.actualVersion,
            changedSections: affectedEntity.changedSections,
            eventKind: 'npc.changed',
            sessionId: command.sessionId,
            battleId: command.battleId,
          });
        }
      }

      return result;
    } catch (error) {
      this.restoreEntity(process.actorKey, attackerSnapshot);
      this.restoreEntity(process.targetKey, defenderSnapshot);
      for (const [characterId, token] of membershipTokens) {
        restoreMembershipRuntimeToken(command.gameId, characterId, token);
      }
      Object.assign(battle, battleSnapshot);
      throw error;
    }
  }

  private resultOf(
    command: GameCombatCommand,
    process: GameAuthoritativeCommandResult['process'],
    effects: GameCombatEffect[] = [],
    affectedEntities: GameAuthoritativeCommandResult['affectedEntities'] = [],
  ): GameAuthoritativeCommandResult {
    return {
      commandId: command.commandId,
      status: process.status === 'applied' ? 'applied' : 'accepted',
      battleId: command.battleId,
      process,
      effects,
      affectedEntities,
      conflict: null,
    };
  }

  private conflictResult(
    command: GameCombatCommand,
    battle: { battleStateVersion: number; combatProcesses: Record<string, GameCombatProcessState> } | null,
    code: GameCombatConflict['code'],
    currentEntityVersions: Record<CombatEntityKey, number> = {},
    currentSessionStateVersion: number | null = null,
    currentBattleStateVersion: number | null = battle?.battleStateVersion ?? null,
  ): GameAuthoritativeCommandResult {
    const process = command.processId ? battle?.combatProcesses[command.processId] : undefined;

    return {
      commandId: command.commandId,
      status: 'rejected',
      battleId: command.battleId,
      process: {
        processId: command.processId,
        offerId: command.offerId,
        status: 'rejected',
        processStateVersion: process?.processStateVersion ?? 0,
      },
      effects: [],
      affectedEntities: [],
      conflict: {
        code,
        currentSessionStateVersion,
        currentBattleStateVersion,
        currentProcessStateVersion: process?.processStateVersion ?? null,
        currentEntityVersions,
        retriable: code === 'stale_version',
      },
    };
  }

  private remember(command: GameCombatCommand, fingerprint: string, result: GameAuthoritativeCommandResult): void {
    const record: GameCombatCommandRecord = {
      commandId: command.commandId,
      fingerprint,
      sessionId: command.sessionId,
      battleId: command.battleId,
      processId: result.process.processId,
      result: cloneData(result),
    };
    rememberMockCombatCommandRecord(command.gameId, record);
  }

  private validateExpectedEntityVersions(
    command: GameCombatCommand,
    entityKeys: CombatEntityKey[],
  ): Record<CombatEntityKey, number> | null {
    const currentEntityVersions: Record<CombatEntityKey, number> = {};
    for (const entityKey of entityKeys) {
      const entity = this.readEntity(entityKey);
      const expectedVersion = command.expectedEntityVersions[entityKey];
      if (!entity || expectedVersion === undefined) return {};
      currentEntityVersions[entityKey] = entity.actualVersion;
      if (expectedVersion !== entity.actualVersion) return currentEntityVersions;
    }

    return null;
  }

  private readEntity(entityKey: CombatEntityKey): {
    kind: 'character' | 'npc';
    id: number;
    version: CharacterVersion;
    actualVersion: number;
  } | null {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (!Number.isInteger(id)) return null;

    if (kind === 'character') {
      try {
        const detail = this.characterMutationPort.capture(id);

        return {
          kind,
          id,
          version: detail.version,
          actualVersion: detail.actualVersion,
        };
      } catch {
        return null;
      }
    }

    if (kind === 'npc') {
      try {
        const npc = this.npcMutationPort.capture(id);
        if (npc.status !== 'active' || npc.version === null) return null;

        return { kind, id, version: npc.version, actualVersion: npc.actualVersion };
      } catch {
        return null;
      }
    }

    return null;
  }

  private isActiveActor(
    entityKey: CombatEntityKey,
    gameId: number,
    participantEntityKeys: readonly CombatEntityKey[],
  ): boolean {
    if (!participantEntityKeys.includes(entityKey)) return false;
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') return isActiveSessionParticipant(gameId, id);

    const npc = gameNpcs.find((entry) => entry.id === id && entry.gameId === gameId);

    return npc?.status === 'active';
  }

  private captureEntity(entityKey: CombatEntityKey): CharacterDetail | GameNpc {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') return this.characterMutationPort.capture(id);

    return this.npcMutationPort.capture(id);
  }

  private restoreEntity(entityKey: CombatEntityKey, snapshot: unknown): void {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') {
      this.characterMutationPort.restore(id, snapshot as Parameters<IGameCharacterRuntimeMutationPort['restore']>[1]);

      return;
    }

    this.npcMutationPort.restore(id, snapshot as Parameters<IGameNpcRuntimeMutationPort['restore']>[1]);
  }

  private replaceEntity(entityKey: CombatEntityKey, patch: CharacterPatch): void {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') {
      this.characterMutationPort.applyPatch(id, patch);

      return;
    }

    this.npcMutationPort.applyPatch(id, patch);
  }

  private markMembershipMutation(gameId: number, entityKey: CombatEntityKey): void {
    const [kind, rawId] = entityKey.split(':');
    if (kind === 'character') markCharacterRuntimeMutation(gameId, Number(rawId));
  }

  private nextOfferId(battle: {
    checkOffers: { id: number }[];
    combatProcesses: Record<string, GameCombatProcessState>;
  }): number {
    const existingIds = [
      ...battle.checkOffers.map((offer) => offer.id),
      ...Object.values(battle.combatProcesses).map((process) => process.offerId),
    ];

    return Math.max(0, ...existingIds) + 1;
  }

  private fingerprintOf(command: GameCombatCommand): string {
    const { commandId: _commandId, ...request } = command;

    return JSON.stringify(this.normalizeForFingerprint(request));
  }

  private normalizeForFingerprint(value: unknown): unknown {
    if (Array.isArray(value)) return value.map((entry) => this.normalizeForFingerprint(entry));
    if (value === null || typeof value !== 'object') return value;

    return Object.fromEntries(
      Object.entries(value)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([key, entry]) => [key, this.normalizeForFingerprint(entry)]),
    );
  }
}
