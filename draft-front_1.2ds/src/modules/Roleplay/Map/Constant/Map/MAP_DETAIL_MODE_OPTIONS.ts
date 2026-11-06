import type { MapDetailMode } from '@/modules/Roleplay/Map/Enum/MapDetailMode';

/** Режимы страницы карты. */
export const MAP_DETAIL_MODE_OPTIONS: { value: MapDetailMode; title: string; icon: string }[] = [
  { value: 'view', title: 'Просмотр', icon: 'mdi-eye-outline' },
  { value: 'edit', title: 'Редактор', icon: 'mdi-pencil-outline' },
  { value: 'route', title: 'Маршрут', icon: 'mdi-map-marker-path' },
];
