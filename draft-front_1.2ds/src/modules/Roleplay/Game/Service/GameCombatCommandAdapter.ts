import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';
import type { GameCombatCommand } from '@/modules/Roleplay/Game/Dto/GameCombatCommand';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';

/**
 * Формирует пользовательские combat decisions и отправляет их через Game API.
 * Adapter не вычисляет outcome и не вызывает Character/overlay/Chat mutations.
 * `commandId` принадлежит вызывающему коду и должен сохраняться для retry.
 */
export class GameCombatCommandAdapter {
  constructor(private readonly gameApi: Pick<IGameApi, 'submitCombatCommand'>) {}

  submitAttack(
    command: Extract<GameCombatCommand, { commandType: 'attackDecision' }>,
    signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    return this.gameApi.submitCombatCommand(command, signal);
  }

  submitDefense(
    command: Extract<GameCombatCommand, { commandType: 'defenseDecision' }>,
    signal?: AbortSignal,
  ): Promise<GameAuthoritativeCommandResult> {
    return this.gameApi.submitCombatCommand(command, signal);
  }

  changedEntityKeys(result: GameAuthoritativeCommandResult): string[] {
    return result.affectedEntities.map((entity) => `${entity.kind}:${entity.id}`);
  }
}
