import type { MapLineDecor } from '@/modules/Roleplay/Map/Dto/MapLineDecor';
import type { MapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import type { MapLineSegment } from '@/modules/Roleplay/Map/Dto/MapLineSegment';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

export interface MapLine {
  id: number;
  variantId: number;
  color: string;
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  points: MapPoint[];
  widths: number[];
  handles: MapLineHandle[];
  /** Стили сегментов; пусто — везде цвет и скорость ленты. */
  segments: MapLineSegment[];
  /** Ширина градиента между цветами, px; 0 — резкая граница. */
  colorBlend: number;
  /** Визуальный декор; пусто — без декора. */
  decor: MapLineDecor;
  label: string;
  isPresent: boolean;
  sortOrder: number;
}
