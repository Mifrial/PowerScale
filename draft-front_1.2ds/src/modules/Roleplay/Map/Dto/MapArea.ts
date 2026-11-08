import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';

export interface MapArea {
  id: number;
  layer: MapAreaLayer;
  color: string;
  /** Цвет границы `#rrggbb`; пусто или нет — границы нет. */
  stroke?: string;
  /** Толщина границы, экранные пиксели; без границы не рисуется. */
  strokeWidth?: number;
  /** Визуальный декор; пусто — без декора. */
  decor: MapAreaDecor;
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  label: string;
  nodeId: number | null;
  /** Группа однотипных областей; null — без группы. */
  groupId: number | null;
  sortOrder: number;
  shapes: MapAreaShape[];
}
