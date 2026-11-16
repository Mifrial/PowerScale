import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Комната — замкнутая грань сети стен. Выводится из геометрии, не хранится. */
export interface SceneRoom {
  /** Контур грани по осям стен, единицы сцены. */
  points: ScenePoint[];
  /** Площадь в единицах сцены². */
  area: number;
  /** Стены, образующие грань. */
  wallIds: number[];
  /** Всё, что двигается вместе с комнатой: стены, проёмы, пол и содержимое. */
  elementIds: number[];
  /** Полы внутри грани. */
  floorIds: number[];
}
