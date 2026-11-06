import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';

/** Шаблон области: заготовка полей, а не отдельный тип. */
export interface MapAreaPreset {
  code: string;
  title: string;
  layer: MapAreaLayer;
  color: string;
  /** Проходимость в хранимой шкале. */
  speed: number;
}

/** Типовые объекты и поверхности. */
export const MAP_AREA_PRESETS: MapAreaPreset[] = [
  { code: 'house', title: 'Дом', layer: 'object', color: '#b08968', speed: 0 },
  { code: 'wall', title: 'Стена', layer: 'object', color: '#616161', speed: 0 },
  { code: 'gate', title: 'Ворота', layer: 'object', color: '#c9a227', speed: 10000 },
  { code: 'stall', title: 'Прилавок', layer: 'object', color: '#d4a373', speed: 10000 },
  { code: 'bridge', title: 'Мост', layer: 'object', color: '#9a8b73', speed: 10000 },
  { code: 'tower', title: 'Башня', layer: 'object', color: '#8d7b68', speed: 0 },
  { code: 'grass', title: 'Луг', layer: 'surface', color: '#9AC385', speed: 5000 },
  { code: 'forest', title: 'Лес', layer: 'surface', color: '#4E7A4E', speed: 2000 },
  { code: 'field', title: 'Поле', layer: 'surface', color: '#C7C46A', speed: 6000 },
  { code: 'rock', title: 'Гора', layer: 'surface', color: '#7A6A5A', speed: 0 },
  { code: 'square', title: 'Площадь', layer: 'surface', color: '#C9B79C', speed: 8000 },
  { code: 'sand', title: 'Песок', layer: 'surface', color: '#D9C08A', speed: 4000 },
  { code: 'swamp', title: 'Болото', layer: 'surface', color: '#6B7050', speed: 1500 },
  { code: 'lake', title: 'Озеро', layer: 'surface', color: '#8FB8C9', speed: 0 },
];
