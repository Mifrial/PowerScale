/** Карта видимости клеток поля из точки обзора. */
export interface SceneVisibility {
  columns: number;
  rows: number;
  /** 1 — клетка видна, 0 — скрыта. Row-major, как у сетки поверхностей. */
  visible: Uint8Array;
}
