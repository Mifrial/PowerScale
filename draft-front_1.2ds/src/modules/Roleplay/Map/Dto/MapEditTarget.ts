/** Редакторская цель внутри выбранного объекта: вершина, ручка Безье или ширина. */
export type MapEditTarget =
  | { kind: 'area-vertex'; id: number; index: number }
  | { kind: 'area-handle-in'; id: number; index: number }
  | { kind: 'area-handle-out'; id: number; index: number }
  | { kind: 'line-vertex'; id: number; index: number }
  | { kind: 'line-handle-in'; id: number; index: number }
  | { kind: 'line-handle-out'; id: number; index: number }
  | { kind: 'line-width'; id: number; index: number };

/** Вершина объекта в редакторе. */
export type MapVertexTarget = Extract<
  MapEditTarget,
  { kind: 'area-vertex' | 'line-vertex' }
>;
