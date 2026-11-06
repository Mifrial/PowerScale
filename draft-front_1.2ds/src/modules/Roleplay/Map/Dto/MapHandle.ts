import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Ручки Безье узла: in и out — смещения относительно точки. */
export interface MapHandle {
  in: MapPoint;
  out: MapPoint;
}

/** Нулевые ручки для новой точки. */
export function zeroMapHandle(): MapHandle {
  return { in: [0, 0], out: [0, 0] };
}
