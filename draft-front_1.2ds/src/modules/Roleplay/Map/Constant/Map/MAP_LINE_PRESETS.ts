import type { MapLineDecor } from '@/modules/Roleplay/Map/Dto/MapLineDecor';
import { MAP_LINE_FLOW_DEFAULTS } from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DECOR';

/** Шаблон ленты: цвет, скорость и ширина по умолчанию. */
export interface MapLinePreset {
  code: string;
  title: string;
  color: string;
  /** Проходимость в хранимой шкале. */
  speed: number;
  width: number;
  /** Ширина перехода между цветами; не задана — умолчание ленты. */
  colorBlend?: number;
  /** Визуальный декор; не задан — без декора. */
  decor?: MapLineDecor;
}

/** Типовые реки и дороги. */
export const MAP_LINE_PRESETS: MapLinePreset[] = [
  { code: 'deep-river', title: 'Глубокая река', color: '#3F6F8F', speed: 400, width: 16 },
  {
    code: 'calm-river',
    title: 'Спокойная река',
    color: '#5A87A8',
    speed: 800,
    width: 14,
    colorBlend: 16,
    decor: { flow: { ...MAP_LINE_FLOW_DEFAULTS } },
  },
  { code: 'river', title: 'Река', color: '#5A87A8', speed: 800, width: 14 },
  { code: 'stream', title: 'Ручей', color: '#6FA8C4', speed: 1500, width: 6 },
  { code: 'ford', title: 'Брод', color: '#B7A98A', speed: 1000, width: 10 },
  { code: 'dirt-road', title: 'Грунтовая дорога', color: '#B8A57A', speed: 7000, width: 10 },
  { code: 'stone-road', title: 'Каменная дорога', color: '#C5C6C6', speed: 10000, width: 10 },
  { code: 'path', title: 'Тропа', color: '#B8A57A', speed: 6000, width: 6 },
];
