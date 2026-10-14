import type { GameInitiative, GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { GameInitiativeOrderEntry } from '@/modules/Roleplay/Game/Dto/GameInitiativeOrderEntry';
import type { GameInitiativeRoll } from '@/modules/Roleplay/Game/Dto/GameInitiativeRoll';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';

/**
 * Цепочка ведущего: сессия, бой выбранным составом, бросок инициативы на сервере.
 */
export class GameInitiativeLaunchService {
  /** Открывает сессию и бой при необходимости и бросает инициативу. */
  async launch(
    api: IGameApi,
    gameId: number,
    sessionRunning: boolean,
    rolls: GameInitiativeRoll[],
    names: Map<string, string>,
  ): Promise<{ scale: GameInitiative; orderMessage: string; firstName: string | null }> {
    if (rolls.length === 0) {
      throw new Error('Выберите хотя бы одного участника');
    }

    if (gamePhpBattleContext.battleId !== null) {
      throw new Error('Бой уже открыт');
    }

    if (!sessionRunning) {
      const sessionResult = await api.startGameSession({
        commandId: createRandomId(),
        commandType: 'startSession',
        gameId,
        sessionId: null,
        battleId: null,
        participantAdmission: 'currentEligible',
        payload: {},
      });
      if (sessionResult.kind === 'conflict') {
        throw new Error(sessionResult.conflict.code);
      }
    }

    const participants = rolls.map((roll) => ({ type: roll.type, id: roll.id }));
    const battleResult = await api.startGameBattle({
      commandId: createRandomId(),
      commandType: 'startBattle',
      gameId,
      sessionId: 'php',
      battleId: null,
      expectedSessionStateVersion: 1,
      payload: {},
      participants,
    });
    if (battleResult.kind === 'conflict') {
      throw new Error(battleResult.conflict.code);
    }

    const battleId = gamePhpBattleContext.battleId;
    if (battleId === null) {
      throw new Error('Бой не открыт');
    }

    const rolled = await api.rollInitiative({
      gameId,
      battleId,
      idempotencyKey: createRandomId(),
      expectedVersion: gamePhpBattleContext.battleVersion,
      rolls,
    });

    const ordered = this.participantsOf(rolled.order, names);
    const scale: GameInitiative = {
      gameId,
      active: true,
      participants: ordered,
      activeIndex: 0,
      round: 1,
      updatedAt: '',
    };

    return {
      scale,
      orderMessage: this.orderMessage(rolled.order, ordered),
      firstName: ordered[0]?.name ?? null,
    };
  }

  /** Собирает шкалу из ответа сервера. */
  participantsOf(order: GameInitiativeOrderEntry[], names: Map<string, string>): GameInitiativeParticipant[] {
    return order.map((entry) => {
      const key = `${entry.type}:${entry.id}`;

      return {
        id: key,
        name: names.get(key) ?? key,
        kind: entry.type,
        entityId: entry.id,
      };
    });
  }

  /** Текст порядка без выдуманных кубов. */
  orderMessage(order: GameInitiativeOrderEntry[], participants: GameInitiativeParticipant[]): string {
    const byKey = new Map(order.map((entry) => [`${entry.type}:${entry.id}`, entry]));
    const parts = participants.map((participant) => {
      const entry = byKey.get(participant.id);
      const score =
        entry?.roll !== null && entry?.roll !== undefined
          ? new DimensionalNumber(entry.roll).toString()
          : entry?.rating != null
            ? String(entry.rating)
            : '—';

      return `${participant.name} (${score})`;
    });

    return `Порядок инициативы: ${parts.join(', ')}.`;
  }
}
