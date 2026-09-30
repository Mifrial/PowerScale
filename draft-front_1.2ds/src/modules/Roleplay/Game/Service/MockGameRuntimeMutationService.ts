import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameRuntimeMutationCommand } from '@/modules/Roleplay/Game/Dto/GameRuntimeMutationCommand';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { IGameCharacterRuntimeMutationPort } from '@/modules/Roleplay/Game/Interface/IGameCharacterRuntimeMutationPort';
import type { IGameNpcRuntimeMutationPort } from '@/modules/Roleplay/Game/Interface/IGameNpcRuntimeMutationPort';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import { gameCharacterMemberships } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';

type RuntimeSnapshot = CharacterDetail | GameNpc;

interface RuntimeCommandRecord {
  fingerprint: string;
  result: GameAuthoritativeCommandResult;
}

/** Применяет typed actual-команды в mock Game с CAS, replay и rollback для batch. */
export class MockGameRuntimeMutationService {
  private readonly commandRecords = new Map<string, RuntimeCommandRecord>();

  constructor(
    private readonly characterPort: IGameCharacterRuntimeMutationPort,
    private readonly npcPort: IGameNpcRuntimeMutationPort,
  ) {}

  apply(command: GameRuntimeMutationCommand): GameAuthoritativeCommandResult {
    const key = `${command.gameId}:${command.commandId}`;
    const fingerprint = this.fingerprintOf(command);
    const previous = this.commandRecords.get(key);
    if (previous) {
      if (previous.fingerprint !== fingerprint) throw new Error('Повторный commandId имеет другой payload');

      return cloneData(previous.result);
    }

    this.assertEntityBelongsToGame(command);
    const snapshot = this.capture(command.entityKey);
    try {
      this.applyPatch(command);
      const result = this.resultOf(command);
      this.commandRecords.set(key, { fingerprint, result: cloneData(result) });

      return result;
    } catch (error) {
      this.restore(command.entityKey, snapshot);
      throw error;
    }
  }

  applyBatch(commands: GameRuntimeMutationCommand[]): GameAuthoritativeCommandResult[] {
    const snapshots = new Map<string, RuntimeSnapshot>();
    const newlyApplied: string[] = [];
    try {
      return commands.map((command) => {
        const key = command.entityKey;
        if (!snapshots.has(key)) snapshots.set(key, this.capture(key));
        const result = this.apply(command);
        newlyApplied.push(`${command.gameId}:${command.commandId}`);

        return result;
      });
    } catch (error) {
      for (const [entityKey, snapshot] of snapshots)
        this.restore(entityKey as GameRuntimeMutationCommand['entityKey'], snapshot);
      for (const commandKey of newlyApplied) this.commandRecords.delete(commandKey);
      throw error;
    }
  }

  private applyPatch(command: GameRuntimeMutationCommand): void {
    const [kind, rawId] = command.entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') {
      this.characterPort.applyPatch(id, command.patch);

      return;
    }
    if (kind === 'npc') {
      this.npcPort.applyPatch(id, command.patch);

      return;
    }

    throw new Error(`Неизвестный тип runtime entity: ${kind}`);
  }

  private capture(entityKey: GameRuntimeMutationCommand['entityKey']): RuntimeSnapshot {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') return this.characterPort.capture(id);
    if (kind === 'npc') return this.npcPort.capture(id);

    throw new Error(`Неизвестный тип runtime entity: ${kind}`);
  }

  private restore(entityKey: GameRuntimeMutationCommand['entityKey'], snapshot: RuntimeSnapshot): void {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') {
      this.characterPort.restore(id, snapshot as CharacterDetail);

      return;
    }
    if (kind === 'npc') {
      this.npcPort.restore(id, snapshot as GameNpc);

      return;
    }

    throw new Error(`Неизвестный тип runtime entity: ${kind}`);
  }

  private assertEntityBelongsToGame(command: GameRuntimeMutationCommand): void {
    const [kind, rawId] = command.entityKey.split(':');
    const id = Number(rawId);
    if (kind === 'character') {
      if (
        !gameCharacterMemberships.some(
          (membership) => membership.gameId === command.gameId && membership.characterId === id,
        )
      )
        throw new Error('Персонаж не входит в игру');

      return;
    }
    if (kind === 'npc') {
      if (!gameNpcs.some((npc) => npc.gameId === command.gameId && npc.id === id))
        throw new Error('НПС не входит в игру');

      return;
    }

    throw new Error(`Неизвестный тип runtime entity: ${kind}`);
  }

  private resultOf(command: GameRuntimeMutationCommand): GameAuthoritativeCommandResult {
    const [kind, rawId] = command.entityKey.split(':');
    const id = Number(rawId);
    const actualVersion =
      kind === 'character' ? this.characterPort.capture(id).actualVersion : this.npcPort.capture(id).actualVersion;

    return {
      commandId: command.commandId,
      status: 'applied',
      battleId: null,
      process: { processId: null, offerId: null, status: 'applied', processStateVersion: 0 },
      effects: [],
      affectedEntities: [
        {
          kind: kind as 'character' | 'npc',
          id,
          actualVersion,
          changedSections: this.changedSections(command.patch.operations),
        },
      ],
      conflict: null,
    };
  }

  private changedSections(operations: GameRuntimeMutationCommand['patch']['operations']): string[] {
    return [
      ...new Set(
        operations.map((operation) => {
          if (operation.kind === 'setField') return operation.field;
          if (operation.kind === 'replaceSection') return operation.section;
          if (operation.kind === 'setResourceCurrent') return 'resources';

          return 'inventory';
        }),
      ),
    ];
  }

  private fingerprintOf(command: GameRuntimeMutationCommand): string {
    return JSON.stringify({
      gameId: command.gameId,
      entityKey: command.entityKey,
      patch: command.patch,
    });
  }
}
