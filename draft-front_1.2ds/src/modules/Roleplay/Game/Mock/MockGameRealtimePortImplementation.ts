import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import type { GameRuntimeEntityChanged } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityChanged';
import type { IGameRealtimePort } from '@/modules/Roleplay/Game/Interface/IGameRealtimePort';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { characterChangePort } from '@/modules/Roleplay/Character/init';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  fetchGameCharacters,
  isActiveSessionParticipant,
  isSessionActive,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import * as mockGameState from '@/modules/Roleplay/Game/Mock/mockGameState';

/**
 * In-memory Game realtime contract: ordered delivery by cursor, без снимка.
 * Снимок — дефект эскиза, в PHP его нет.
 */
export class MockGameRealtimePort implements IGameRealtimePort {
  private readonly eventsByGame = new Map<number, GameRealtimeEvent[]>();
  private readonly listenersByGame = new Map<number, Set<(event: GameRealtimeEvent) => void>>();
  private readonly stopCharacterSubscription: () => void;

  constructor() {
    this.stopCharacterSubscription = characterChangePort.subscribe((change) => {
      void this.publishCharacterChange(change.characterId, change.actualVersion, change.changedSections);
    });
  }

  subscribe(gameId: number, listener: (event: GameRealtimeEvent) => void): () => void {
    const listeners = this.listenersByGame.get(gameId) ?? new Set<(event: GameRealtimeEvent) => void>();
    listeners.add(listener);
    this.listenersByGame.set(gameId, listeners);

    return () => {
      listeners.delete(listener);
      if (listeners.size === 0) this.listenersByGame.delete(gameId);
    };
  }

  publishEntityChange(change: GameRuntimeEntityChanged): void {
    const event = this.appendEvent(change);
    this.notify(event);
  }

  dispose(): void {
    this.stopCharacterSubscription();
    this.listenersByGame.clear();
    this.eventsByGame.clear();
  }

  reset(): void {
    this.eventsByGame.clear();
    this.listenersByGame.clear();
  }

  private async publishCharacterChange(
    characterId: number,
    actualVersion: number,
    changedSections: string[],
  ): Promise<void> {
    for (const detail of gameDetails) {
      const gameId = detail.game.id;
      if (!isSessionActive(gameId) || !isActiveSessionParticipant(gameId, characterId)) continue;

      const memberships = await fetchGameCharacters(gameId, undefined, [characterId]);
      const membership = memberships.find((entry) => entry.characterId === characterId);
      if (!membership || membership.membershipStatus !== 'active') continue;
      const snapshot = await mockGameState.getGameStateSnapshot(gameId);
      if (
        snapshot.session &&
        !snapshot.session.participantEntityKeys.includes(`character:${characterId}` as CombatEntityKey)
      )
        continue;
      this.publishEntityChange({
        gameId,
        entityKey: `character:${characterId}` as CombatEntityKey,
        actualVersion,
        changedSections,
        eventKind: 'character.changed',
        sessionId: snapshot.session?.sessionId ?? null,
        battleId: snapshot.battle?.battleId ?? null,
      });
    }
  }

  private appendEvent(change: GameRuntimeEntityChanged): GameRealtimeEvent {
    const events = this.eventsByGame.get(change.gameId) ?? [];
    const cursor = (events.at(-1)?.cursor ?? 0) + 1;
    const event: GameRealtimeEvent = {
      ...change,
      eventId: `${change.gameId}.${cursor}`,
      cursor,
    };
    events.push(event);
    if (events.length > 100) events.shift();
    this.eventsByGame.set(change.gameId, events);

    return event;
  }

  private notify(event: GameRealtimeEvent): void {
    for (const listener of this.listenersByGame.get(event.gameId) ?? []) {
      listener(event);
    }
  }
}
