import type { MapAreaKind } from '@/modules/Roleplay/Map/Enum/MapAreaKind';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';

/** Правка области в черновике. */
export interface MapAreaEdit {
  layer: MapAreaLayer;
  terrainCode: string | null;
  kind: MapAreaKind;
  label: string;
  nodeId: number | null;
  blocksMovement: boolean;
  /** Создать точку интереса в центроиде области. */
  makePoi?: boolean;
}
