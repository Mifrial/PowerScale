import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Полигон области в варианте: вершины и ручки Безье замкнутого контура. */
export interface MapAreaShape {
  variantId: number;
  path: MapPoint[];
  /** Ручки по вершинам; пусто — прямые рёбра. */
  handles: MapHandle[];
  isPresent: boolean;
}
