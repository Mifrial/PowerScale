import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Ручки Безье узла ленты: in и out — смещения относительно точки. */
export interface MapLineHandle {
  in: MapPoint;
  out: MapPoint;
}

/** Нулевые ручки для новой точки. */
export function zeroMapLineHandle(): MapLineHandle {
  return { in: [0, 0], out: [0, 0] };
}
