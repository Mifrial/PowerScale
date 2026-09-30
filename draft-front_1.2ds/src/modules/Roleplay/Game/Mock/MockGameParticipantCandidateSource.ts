import type { GameParticipantCandidateQuery } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidateQuery';
import type { GameParticipantCandidateResult } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidateResult';
import { getGameStateSnapshot, isGameSessionParticipant } from '@/modules/Roleplay/Game/Mock/mockGameState';
import {
  fetchGameCharacterCandidatePage,
  isMembershipEligibleForSession,
} from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { fetchNpcCandidatePage } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';

/** Mock boundary для bounded server-side поиска участников без передачи полных листов. */
export class MockGameParticipantCandidateSource {
  async search(query: GameParticipantCandidateQuery): Promise<GameParticipantCandidateResult> {
    const snapshot = await getGameStateSnapshot(query.gameId);
    const sessionKeys = new Set(snapshot.session?.participantEntityKeys ?? []);
    const hasSession = snapshot.session !== null;
    const limit = Math.min(Math.max(query.limit ?? 20, 1), 50);
    const offsets = this.parseCursor(query.cursor);
    const [characterPage, npcPage] = await Promise.all([
      fetchGameCharacterCandidatePage(
        query.gameId,
        query.query,
        offsets.characterOffset,
        limit,
        (membership) => {
          const entityKey = `character:${membership.characterId}` as const;
          const isParticipant = hasSession && sessionKeys.has(entityKey);

          return isParticipant || (!hasSession && isMembershipEligibleForSession(membership, query.gameId));
        },
      ),
      fetchNpcCandidatePage(
        query.gameId,
        query.query,
        offsets.npcOffset,
        limit,
        (npc) => {
          if (npc.status !== 'active') return false;

          const entityKey = `npc:${npc.id}` as const;

          return !hasSession || isGameSessionParticipant(query.gameId, entityKey);
        },
      ),
    ]);
    const candidates = [
      ...characterPage.items.map((membership) => {
        const entityKey = `character:${membership.characterId}` as const;
        const isParticipant = hasSession && sessionKeys.has(entityKey);

        return {
          source: 'character' as const,
          candidate: {
            entityKey,
            kind: 'character' as const,
            id: membership.characterId,
            name: membership.characterName,
            status: 'active' as const,
            availability: isParticipant ? ('session-participant' as const) : ('admissible' as const),
          },
        };
      }),
      ...npcPage.items.map((npc) => {
        const entityKey = `npc:${npc.id}` as const;
        const isParticipant = hasSession && isGameSessionParticipant(query.gameId, entityKey);

        return {
          source: 'npc' as const,
          candidate: {
            entityKey,
            kind: 'npc' as const,
            id: npc.id,
            name: npc.name,
            status: 'active' as const,
            availability: isParticipant ? ('session-participant' as const) : ('admissible' as const),
          },
        };
      }),
    ].sort((left, right) => {
      const sourceOrder = left.source === right.source ? 0 : left.source === 'character' ? -1 : 1;
      if (sourceOrder !== 0) return sourceOrder;

      const nameOrder = left.candidate.name.localeCompare(right.candidate.name, 'ru');

      return nameOrder !== 0 ? nameOrder : left.candidate.id - right.candidate.id;
    });
    const items = candidates.slice(0, limit).map((entry) => entry.candidate);
    const consumedCharacterCount = candidates
      .slice(0, items.length)
      .filter((entry) => entry.source === 'character').length;
    const consumedNpcCount = candidates.slice(0, items.length).filter((entry) => entry.source === 'npc').length;
    const nextCharacterOffset = offsets.characterOffset + consumedCharacterCount;
    const nextNpcOffset = offsets.npcOffset + consumedNpcCount;
    const hasUnconsumedPageItems = items.length < candidates.length;
    const hasMoreSourceItems = characterPage.nextOffset !== null || npcPage.nextOffset !== null;

    return {
      items,
      nextCursor:
        hasUnconsumedPageItems || hasMoreSourceItems
          ? this.createCursor(nextCharacterOffset, nextNpcOffset)
          : null,
    };
  }

  private parseCursor(cursor: string | undefined): { characterOffset: number; npcOffset: number } {
    if (!cursor) return { characterOffset: 0, npcOffset: 0 };

    const [rawCharacterOffset, rawNpcOffset] = cursor.split(':').map(Number);

    return {
      characterOffset: Number.isInteger(rawCharacterOffset) && rawCharacterOffset >= 0 ? rawCharacterOffset : 0,
      npcOffset: Number.isInteger(rawNpcOffset) && rawNpcOffset >= 0 ? rawNpcOffset : 0,
    };
  }

  private createCursor(characterOffset: number, npcOffset: number): string {
    return `${characterOffset}:${npcOffset}`;
  }
}
