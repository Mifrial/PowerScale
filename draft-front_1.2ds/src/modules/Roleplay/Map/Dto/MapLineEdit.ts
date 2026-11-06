import type { MapLineSegment } from '@/modules/Roleplay/Map/Dto/MapLineSegment';

/** Правка ленты в черновике. */
export interface MapLineEdit {
  color: string;
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  /** Ширина одной точки или всех точек при widthAll. */
  width: number;
  /** Применить ширину ко всем точкам ленты. */
  widthAll: boolean;
  label: string;
  /** Стили сегментов; пусто — везде цвет и скорость ленты. */
  segments: MapLineSegment[];
}
