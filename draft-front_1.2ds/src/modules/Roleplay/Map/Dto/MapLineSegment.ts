/** Стиль одного сегмента ленты; цвет null — цвет ленты. */
export interface MapLineSegment {
  /** Проходимость: 0 — непроходимо. */
  speed: number;
  color: string | null;
}
