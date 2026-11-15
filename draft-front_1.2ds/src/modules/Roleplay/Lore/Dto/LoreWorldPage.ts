import type { LoreWorld } from '@/modules/Roleplay/Lore/Dto/LoreWorld';

/** Страница миров. */
export interface LoreWorldPage {
  items: LoreWorld[];
  total: number;
}
