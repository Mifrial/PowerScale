/** Часть элемента, которую редактируют вершинами. */
export type SceneVertexPart = 'shape' | 'footprint' | 'silhouette';

/** Зона декора: основание или зона непрозрачности. */
export type SceneDecorZone = 'footprint' | 'silhouette';

/** Точка, которую сейчас редактируют ручками. */
export interface SceneVertexTarget {
  elementId: number;
  /** Геометрия элемента, основание или силуэт декора; по умолчанию — фигура. */
  part?: SceneVertexPart;
  index: number;
}
