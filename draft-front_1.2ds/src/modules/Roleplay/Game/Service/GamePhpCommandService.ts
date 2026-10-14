import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';
import type { GamePhpWoundParty } from '@/modules/Roleplay/Game/Dto/GamePhpWoundParty';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';

/**
 * Собирает тела PHP-команд боя из контекста P4.
 */
export class GamePhpCommandService {
  participantOf(key: CombatEntityKey): GamePhpParticipant {
    const parsed = combatCardModelService.parseCombatEntityKey(key);

    return { type: parsed.kind, id: parsed.id };
  }

  woundPartyOf(key: CombatEntityKey): GamePhpWoundParty {
    const parsed = combatCardModelService.parseCombatEntityKey(key);

    return { kind: parsed.kind, id: parsed.id };
  }

  woundPartyFromHeldBy(heldBy: unknown): GamePhpWoundParty {
    if (heldBy !== null && typeof heldBy === 'object' && !Array.isArray(heldBy)) {
      const record = heldBy as Record<string, unknown>;
      if ((record.kind === 'character' || record.kind === 'npc') && typeof record.id === 'number' && record.id >= 1) {
        return { kind: record.kind, id: record.id };
      }
    }
    if (typeof heldBy === 'string') {
      if (heldBy.startsWith('npc:')) return { kind: 'npc', id: Number(heldBy.slice(4)) };
      if (heldBy.startsWith('character:')) return { kind: 'character', id: Number(heldBy.slice(10)) };
    }

    throw new Error('Неизвестный держатель раны');
  }

  uniqueTargetKeys(keys: CombatEntityKey[]): CombatEntityKey[] {
    const seen = new Set<CombatEntityKey>();
    const unique: CombatEntityKey[] = [];
    for (const key of keys) {
      if (seen.has(key)) continue;
      seen.add(key);
      unique.push(key);
    }

    return unique;
  }

  isWideStrike(_mode: string | undefined, targetKeys: CombatEntityKey[]): boolean {
    return this.uniqueTargetKeys(targetKeys).length > 1;
  }

  turnBody(
    gameId: number,
    entityKey: CombatEntityKey,
  ): {
    gameId: number;
    idempotencyKey: string;
    battleId: number;
    expectedBattleVersion: number;
    expectedSheetVersion: number;
  } {
    const battleId = gamePhpBattleContext.battleId;
    if (battleId === null) throw new Error('Бой ещё не известен');

    return {
      gameId,
      idempotencyKey: createRandomId(),
      battleId,
      expectedBattleVersion: gamePhpBattleContext.battleVersion,
      expectedSheetVersion: gamePhpBattleContext.sheetVersionByKey[entityKey] ?? 1,
    };
  }

  async continueOrAbort(api: IGameApi, gameId: number, entityKey: CombatEntityKey, abort: boolean): Promise<void> {
    const body = this.turnBody(gameId, entityKey);
    if (abort) {
      await api.abortAction(body);

      return;
    }
    await api.continueAction(body);
  }
}
