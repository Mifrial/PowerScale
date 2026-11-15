import type { LoreWorldState } from '@/modules/Roleplay/Lore/Enum/LoreWorldState';
import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';

/** Запрос страницы миров. */
export interface LoreWorldQuery {
  limit: number;
  offset: number;
  q?: string;
  state?: LoreWorldState;
  visibility?: LoreWorldVisibility;
}
