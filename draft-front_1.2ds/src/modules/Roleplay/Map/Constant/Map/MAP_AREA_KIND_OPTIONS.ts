import type { MapAreaKind } from '@/modules/Roleplay/Map/Enum/MapAreaKind';

/** Подписи видов областей карты. */
export const MAP_AREA_KIND_OPTIONS: { value: MapAreaKind; title: string }[] = [
  { value: 'building', title: 'Здание' },
  { value: 'bazaar', title: 'Базар' },
  { value: 'park', title: 'Парк' },
  { value: 'water', title: 'Вода' },
  { value: 'wall', title: 'Стена' },
  { value: 'other', title: 'Другое' },
];
