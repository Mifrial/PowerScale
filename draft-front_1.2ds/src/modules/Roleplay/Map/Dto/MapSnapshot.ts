import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapEdge } from '@/modules/Roleplay/Map/Dto/MapEdge';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapSaveVariant } from '@/modules/Roleplay/Map/Dto/MapSaveVariant';
import type { MapVisibility } from '@/modules/Roleplay/Map/Enum/MapVisibility';

/** Снимок карты для `map.save`. */
export interface MapSnapshot {
  name: string;
  description: string;
  visibility: MapVisibility;
  variants: MapSaveVariant[];
  nodes: MapNode[];
  areas: MapArea[];
  lines: MapLine[];
  edges: MapEdge[];
}
