import type { MapAreaKind } from '@/modules/Roleplay/Map/Enum/MapAreaKind';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';

export interface MapArea {
  id: number;
  layer: MapAreaLayer;
  terrainCode: string | null;
  kind: MapAreaKind;
  label: string;
  nodeId: number | null;
  blocksMovement: boolean;
  shapes: MapAreaShape[];
}
