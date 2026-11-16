import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Строение — связная сеть стен и всё внутри её внешнего контура. Выводится из геометрии. */
export interface SceneBuilding {
  /** Внешний контур строения по осям стен. */
  points: ScenePoint[];
  /** Площадь внешнего контура в единицах сцены². */
  area: number;
  /** Стены строения. */
  wallIds: number[];
  /** Всё, что двигается вместе со строением: стены, проёмы, полы и содержимое. */
  elementIds: number[];
}
