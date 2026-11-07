import type { MapVisibility } from '@/modules/Roleplay/Map/Enum/MapVisibility';

/** Поля шапки карты. */
export interface MapMeta {
  name: string;
  description: string;
  visibility: MapVisibility;
  /** Плотность заливки объектов по умолчанию, 0–100. */
  fillOpacity: number;
}
