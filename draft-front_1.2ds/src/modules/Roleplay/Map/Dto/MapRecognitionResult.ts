import type { MapRecognitionObject } from '@/modules/Roleplay/Map/Dto/MapRecognitionObject';

/** Итог распознавания: принятые пятна и статистика отсева. */
export interface MapRecognitionResult {
  objects: MapRecognitionObject[];
  /** Сколько пятен нашли до фильтров. */
  found: number;
  skippedSmall: number;
  skippedLarge: number;
  /** Контуры, выродившиеся в линию: тонкие полоски не создаём объектами. */
  skippedThin: number;
  /** Среднее число вершин среди принятых контуров. */
  meanVertices: number;
  /** Максимальное число вершин среди принятых контуров. */
  maxVertices: number;
  /** Маска принятых пикселей: 1 — совпал цвет; для превью. */
  mask: Uint8Array;
}
