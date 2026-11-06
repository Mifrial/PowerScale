import type { MapLineSegment } from '@/modules/Roleplay/Map/Dto/MapLineSegment';

/** Быстрый стиль сегмента: брод, река, дорога. */
export interface MapSegmentPreset {
  code: string;
  title: string;
  segment: MapLineSegment;
}

/** Частые стили сегментов ленты. */
export const MAP_LINE_SEGMENT_PRESETS: MapSegmentPreset[] = [
  { code: 'river', title: 'Река', segment: { speed: 400, color: '#3F6F8F' } },
  { code: 'shallow', title: 'Мелководье', segment: { speed: 1500, color: '#6FA8C4' } },
  { code: 'ford', title: 'Брод', segment: { speed: 1000, color: '#B7A98A' } },
  { code: 'dirt-road', title: 'Грунтовая дорога', segment: { speed: 7000, color: '#B8A57A' } },
  { code: 'stone-road', title: 'Каменная дорога', segment: { speed: 10000, color: '#C5C6C6' } },
];
