import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';

export interface MapArea {
  id: number;
  layer: MapAreaLayer;
  color: string;
  /** Визуальный декор; пусто — без декора. */
  decor: MapAreaDecor;
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  label: string;
  nodeId: number | null;
  sortOrder: number;
  shapes: MapAreaShape[];
}
