import type { SceneSurfaceKind } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceKind';

/**
 * Сетка поверхностей поля.
 *
 * Клетка равна `occupancyCell` поля и не зависит от пикселей рендера: по этой
 * сетке считаются маршруты и видимость, а рендерер только рисует результат.
 * Все массивы row-major, `index = row * columns + column`.
 */
export interface SceneSurfaceGrid {
  /** Размер клетки в единицах сцены. */
  cell: number;
  /** Левый верхний угол сетки в единицах сцены. */
  x: number;
  y: number;
  columns: number;
  rows: number;
  /** Тип поверхности клетки. */
  kinds: SceneSurfaceKind[];
  /** Множитель скорости: 0 — непроходимо, больше — быстрее. */
  speeds: number[];
  /** Стоимость прохода клетки: `1 / speeds`; `Infinity` — непроходимо. */
  costs: number[];
  /** Клетка перекрывает обзор. */
  opaque: boolean[];
}
