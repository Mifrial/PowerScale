import type { LoreWorldVisibility } from '@/modules/Roleplay/Lore/Enum/LoreWorldVisibility';

/** Мир кодекса в списке и шапке. */
export interface LoreWorld {
  id: number;
  ownerId: number;
  code: string;
  name: string;
  description: string;
  visibility: LoreWorldVisibility;
  spaceCode: string;
  version: number;
  isArchived: boolean;
  canEdit: boolean;
  updatedBy: number | null;
  createdAt: number;
  updatedAt: number;
}
