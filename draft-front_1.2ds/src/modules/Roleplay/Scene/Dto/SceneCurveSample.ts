import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';

/** Сэмпл развёрнутой кривой: точка, единичная касательная и параметр сегмента. */
export interface SceneCurveSample {
  point: ScenePoint;
  /** Единичная касательная в точке. */
  tangent: ScenePoint;
  /** Параметр кубического сегмента, 0…1. */
  t: number;
  /** Номер сегмента между вершинами. */
  segment: number;
}
