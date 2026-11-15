import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';

/** Снимок мета мира для сохранения. */
export interface LoreWorldInput {
  code: string;
  name: string;
  description: string;
  visibility: LoreWorldVisibility;
  spaceCode: string;
}
