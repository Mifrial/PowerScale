import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapWarning } from '@/modules/Roleplay/Map/Dto/MapWarning';

/** Ответ публикации: снимок и предупреждения. */
export interface MapSaveResult extends MapDetail {
  warnings: MapWarning[];
}
