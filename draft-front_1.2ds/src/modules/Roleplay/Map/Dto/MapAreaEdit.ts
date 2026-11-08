import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapNodeEdit } from '@/modules/Roleplay/Map/Dto/MapNodeEdit';

/** Правка области в черновике. */
export interface MapAreaEdit {
  layer: MapAreaLayer;
  color: string;
  /** Цвет границы `#rrggbb`; пусто — границы нет. */
  stroke?: string;
  /** Толщина границы, экранные пиксели. */
  strokeWidth?: number;
  /** Визуальный декор; пусто — наследовать настройку карты. */
  decor: MapAreaDecor;
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  label: string;
  nodeId: number | null;
  /** Группа однотипных областей; null — без группы. */
  groupId: number | null;
  sortOrder: number;
  /** Информация места; null — область без информации. */
  place: MapNodeEdit | null;
}
