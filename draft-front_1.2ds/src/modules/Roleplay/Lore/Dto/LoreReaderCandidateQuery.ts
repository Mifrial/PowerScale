/** Запрос кандидатов в читатели. */
export interface LoreReaderCandidateQuery {
  worldCode: string;
  q?: string;
  limit?: number;
}
