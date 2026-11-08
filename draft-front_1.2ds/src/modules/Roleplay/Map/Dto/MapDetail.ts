import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaGroup } from '@/modules/Roleplay/Map/Dto/MapAreaGroup';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';

export interface MapDetail extends MapSummary {
  canEdit: boolean;
  variants: MapVariant[];
  nodes: MapNode[];
  areas: MapArea[];
  groups: MapAreaGroup[];
  lines: MapLine[];
}
