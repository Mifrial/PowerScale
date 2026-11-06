import type { MapRouteLink } from '@/modules/Roleplay/Map/Dto/MapRouteLink';

/** Список смежности маршрута. */
export type MapRouteAdjacency = Record<number, MapRouteLink[]>;
