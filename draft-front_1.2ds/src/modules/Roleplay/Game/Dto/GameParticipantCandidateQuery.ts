export interface GameParticipantCandidateQuery {
  gameId: number;
  query?: string;
  cursor?: string;
  limit?: number;
}
