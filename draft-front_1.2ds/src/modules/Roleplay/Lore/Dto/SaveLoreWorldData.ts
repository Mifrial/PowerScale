import type { LoreWorldInput } from '@/modules/Roleplay/Lore/Dto/LoreWorldInput';

/** Запрос `lore.saveWorld`: без version — создание. */
export interface SaveLoreWorldData {
  world: LoreWorldInput;
  version?: number;
}
