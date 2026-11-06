import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapNodeEdit } from '@/modules/Roleplay/Map/Dto/MapNodeEdit';

/** Правка области в черновике. */
export interface MapAreaEdit {
  layer: MapAreaLayer;
  color: string;
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  label: string;
  nodeId: number | null;
  sortOrder: number;
  /** Информация места; null — область без информации. */
  place: MapNodeEdit | null;
}
