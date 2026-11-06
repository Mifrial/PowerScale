import type { MapBaseFile } from '@/modules/Roleplay/Map/Dto/MapBaseFile';

export interface MapVariant {
  id: number;
  label: string;
  fromMinutes: number | null;
  sortOrder: number;
  baseFile: MapBaseFile | null;
  millimetersPerPixel: number | null;
  isDefault: boolean;
}
