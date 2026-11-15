import type { SceneSurfaceKind } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceKind';

/** Цвет заливки поверхности на оверлее. */
interface SceneOverlayColor {
  color: number;
  alpha: number;
}

/** Цвета клеток по типу поверхности. */
const COST_COLORS: Record<SceneSurfaceKind, SceneOverlayColor> = {
  grass: { color: 0x22c55e, alpha: 0.14 },
  dirt: { color: 0xf59e0b, alpha: 0.34 },
  stone: { color: 0x94a3b8, alpha: 0.24 },
  sand: { color: 0xfacc15, alpha: 0.22 },
  mud: { color: 0x92400e, alpha: 0.3 },
  ice: { color: 0x67e8f9, alpha: 0.28 },
  water: { color: 0x0ea5e9, alpha: 0.4 },
  bridge: { color: 0xfb923c, alpha: 0.46 },
  none: { color: 0x1e293b, alpha: 0.5 },
};

/**
 * Тактические оверлеи предпросмотра.
 *
 * Цвета не несут игрового смысла: это подсказка автору и ведущему, что дорога
 * и мост быстрее луга, река медленнее, а красное перекрыто препятствием.
 */
export const SCENE_OVERLAY = {
  /** Заливка клеток по типу поверхности. */
  cost: COST_COLORS,

  /** Непроходимая клетка: препятствие, ствол, валун. */
  impassable: { color: 0xef4444, alpha: 0.55 },

  /** Скрытая клетка вдали от границы обзора. */
  hidden: { color: 0x020617, alpha: 0.68 },

  /** Скрытая клетка у самой границы: мягкий переход вместо жёсткой линии. */
  hiddenEdge: { color: 0x020617, alpha: 0.3 },

  /** Второе кольцо от границы: переход между кромкой и плотным туманом. */
  hiddenMid: { color: 0x020617, alpha: 0.48 },

  /** Радиус обзора по умолчанию, ипари. */
  sightIpari: 16,

  /**
   * Грубая клетка наблюдателя для тумана витрины, ипари.
   *
   * Точный расчёт видимости стоит луч на каждую клетку `occupancyCell`, поэтому
   * туман пересобирается по переходу в другую грубую клетку, а не по каждому
   * шагу: сдвиг внутри клетки меняет маску на считанные тексели.
   */
  fogCellIpari: 1,

  /** Минимальный интервал между пересчётами тумана при движении, мс. */
  fogIntervalMs: 250,
} as const;
