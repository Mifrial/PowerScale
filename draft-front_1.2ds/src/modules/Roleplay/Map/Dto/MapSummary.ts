import type { MapVisibility } from '@/modules/Roleplay/Map/Enum/MapVisibility';

export interface MapSummary {
  id: number;
  ownerId: number;
  name: string;
  description: string;
  visibility: MapVisibility;
  isArchived: boolean;
  version: number;
  updatedBy: number | null;
  createdAt: number;
  updatedAt: number;
}
