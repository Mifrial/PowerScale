import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';

/** Мета мира в файле `powerscale.lore`. */
export interface LoreFileWorld {
  code: string;
  name: string;
  description: string;
  visibility: LoreWorldVisibility;
  spaceCode: string;
}
