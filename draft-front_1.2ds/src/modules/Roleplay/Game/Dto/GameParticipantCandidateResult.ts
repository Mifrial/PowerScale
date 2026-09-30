import type { GameParticipantCandidate } from '@/modules/Roleplay/Game/Dto/GameParticipantCandidate';

export interface GameParticipantCandidateResult {
  items: GameParticipantCandidate[];
  nextCursor: string | null;
}
