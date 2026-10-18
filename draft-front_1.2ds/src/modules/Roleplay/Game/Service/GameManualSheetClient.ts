import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameManualSheetRequest } from '@/modules/Roleplay/Game/Dto/GameManualSheetRequest';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';
import { gamePhpCommandService } from '@/modules/Roleplay/Game/Service/Instance/gamePhpCommandService';
import { gamePhpMode } from '@/modules/Roleplay/Game/Service/Instance/gamePhpMode';

/**
 * Ручная правка карточки: PHP и мок через один IGameApi.manualSheet.
 */
export class GameManualSheetClient {
  expectedVersion(entityKey: CombatEntityKey): number {
    const version = gamePhpBattleContext.sheetVersionByKey[entityKey];
    if (typeof version === 'number') return version;
    if (gamePhpMode.isEnabled()) throw new Error('Версия листа неизвестна');

    return 1;
  }

  async apply(
    api: IGameApi,
    gameId: number,
    entityKey: CombatEntityKey,
    operation: GameManualSheetRequest['operation'],
    details: Record<string, unknown>,
  ): Promise<void> {
    const result = await api.manualSheet({
      gameId,
      idempotencyKey: createRandomId(),
      operation,
      target: gamePhpCommandService.woundPartyOf(entityKey),
      expectedSheetVersion: this.expectedVersion(entityKey),
      details,
    });
    gamePhpBattleContext.rememberSheet(entityKey, result.actualVersion);
  }

  resourceDetails(ruleCode: string, current: DimensionalNumberValue): Record<string, unknown> {
    return { resourceCode: ruleCode, current };
  }

  addDetails(state: CharacterStateValue): Record<string, unknown> {
    const details: Record<string, unknown> = { stateRuleCode: state.stateRuleCode };
    if (typeof state.value === 'number') details.value = state.value;
    if (state.dimensionalValue) details.value = state.dimensionalValue;
    if (state.maim) details.maim = state.maim;

    return details;
  }

  patchDetails(state: CharacterStateValue, patch: Record<string, unknown>): Record<string, unknown> {
    if (typeof state.id !== 'number') {
      throw new Error('У состояния нет id листа');
    }

    return { stateRuleCode: state.stateRuleCode, id: state.id, ...patch };
  }

  removeDetails(state: CharacterStateValue): Record<string, unknown> {
    if (typeof state.id !== 'number') {
      throw new Error('У состояния нет id листа');
    }

    return { stateRuleCode: state.stateRuleCode, id: state.id };
  }
}
