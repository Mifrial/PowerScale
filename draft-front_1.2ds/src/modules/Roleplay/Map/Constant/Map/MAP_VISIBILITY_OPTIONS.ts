import type { MapVisibility } from '@/modules/Roleplay/Map/Enum/MapVisibility';

/** Подписи видимости карты. */
export const MAP_VISIBILITY_OPTIONS: { value: MapVisibility; title: string }[] = [
  { value: 'private', title: 'Приватная' },
  { value: 'public', title: 'Публичная' },
];
