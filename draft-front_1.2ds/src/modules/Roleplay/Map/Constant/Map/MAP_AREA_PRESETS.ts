import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaLayer } from '@/modules/Roleplay/Map/Enum/MapAreaLayer';
import { decorFromSet } from '@/modules/Roleplay/Map/Constant/Map/MAP_DECOR';

/** Шаблон области: заготовка полей, а не отдельный тип. */
export interface MapAreaPreset {
  code: string;
  title: string;
  layer: MapAreaLayer;
  color: string;
  /** Проходимость в хранимой шкале. */
  speed: number;
  /** Стартовый декор; нет — область без символов. */
  decor?: MapAreaDecor;
}

/** Типовые объекты и поверхности. */
export const MAP_AREA_PRESETS: MapAreaPreset[] = [
  { code: 'house', title: 'Дом', layer: 'object', color: '#b08968', speed: 0 },
  { code: 'wall', title: 'Стена', layer: 'object', color: '#616161', speed: 0 },
  { code: 'gate', title: 'Ворота', layer: 'object', color: '#c9a227', speed: 10000 },
  { code: 'stall', title: 'Прилавок', layer: 'object', color: '#d4a373', speed: 10000 },
  { code: 'bridge', title: 'Мост', layer: 'object', color: '#9a8b73', speed: 10000 },
  { code: 'tower', title: 'Башня', layer: 'object', color: '#8d7b68', speed: 0 },
  {
    code: 'grass',
    title: 'Луг',
    layer: 'surface',
    color: '#9AC385',
    speed: 5000,
    decor: decorFromSet('grass'),
  },
  {
    code: 'forest',
    title: 'Смешанный лес',
    layer: 'surface',
    color: '#4E7A4E',
    speed: 2000,
    decor: decorFromSet('mixed'),
  },
  {
    code: 'pine',
    title: 'Ельник',
    layer: 'surface',
    color: '#3E6B4A',
    speed: 2000,
    decor: decorFromSet('pine'),
  },
  {
    code: 'grove',
    title: 'Роща',
    layer: 'surface',
    color: '#6F9A5E',
    speed: 3000,
    decor: decorFromSet('tree'),
  },
  {
    code: 'field',
    title: 'Поле',
    layer: 'surface',
    color: '#C7C46A',
    speed: 6000,
    decor: decorFromSet('field'),
  },
  { code: 'rock', title: 'Гора', layer: 'surface', color: '#7A6A5A', speed: 0 },
  { code: 'square', title: 'Площадь', layer: 'surface', color: '#C9B79C', speed: 8000 },
  { code: 'sand', title: 'Песок', layer: 'surface', color: '#D9C08A', speed: 4000 },
  { code: 'swamp', title: 'Болото', layer: 'surface', color: '#6B7050', speed: 1500 },
  { code: 'lake', title: 'Озеро', layer: 'surface', color: '#8FB8C9', speed: 0 },
];
