import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Найденное пятно: контур в пикселях подложки и его средний цвет. */
export interface MapRecognitionObject {
  points: MapPoint[];
  /** Ручки Безье по вершинам; нули — прямые рёбра. */
  handles: MapHandle[];
  /** Площадь пятна, px². */
  area: number;
  /** Средний цвет пятна в `#rrggbb`. */
  color: string;
}
