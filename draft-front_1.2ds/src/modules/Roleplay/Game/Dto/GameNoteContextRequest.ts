import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Параметры контекста заметок игры: срез ревизии и сама игра. */
export interface GameNoteContextRequest {
  rules: Rule[];
  mechanics: Mechanic[];
  spaceId: number | null;
  rulesRevision: number | null;
  gameId: number;
}
